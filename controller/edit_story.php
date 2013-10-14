<?php
/*
 * This file is part of FeedStorm
 * Copyright (C) 2013  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'model/story.php';
require_once 'model/story_edition.php';
require_once 'model/story_media.php';
require_once 'model/story_visit.php';

class edit_story extends fs_controller
{
   public $story;
   public $story_edition;
   public $story_visit;
   public $masterkey;
   
   public function __construct()
   {
      parent::__construct('edit_story', 'Editar historia', 'Editar historia', 'edit_story');
      
      $this->story_edition = new story_edition();
      $this->story_visit = new story_visit();
      
      if( isset($_COOKIE['masterkey']) )
         $this->masterkey = $_COOKIE['masterkey'];
      else if( isset($_POST['masterkey']) )
      {
         $this->masterkey = $_POST['masterkey'];
         setcookie('masterkey', $this->masterkey, time()+86400, FS_PATH);
      }
      else
         $this->masterkey = '';
      
      if( isset($_GET['id']) )
      {
         $story = new story();
         $this->story = $story->get($_GET['id']);
      }
      else
         $this->story = FALSE;
      
      if($this->story)
      {
         if( $this->visitor->human() AND isset($_SERVER['REMOTE_ADDR']) )
         {
            $se0 = $this->story_edition->get_by_params($this->story->get_id(), $this->visitor->get_id());
            if( $se0 )
               $this->story_edition = $se0;
            else
            {
               $this->story_edition->description = $this->story->description;
               $this->story_edition->story_id = $this->story->get_id();
               $this->story_edition->media_id = $this->story->media_id;
               $this->story_edition->title = $this->story->title;
               $this->story_edition->visitor_id = $this->visitor->get_id();
            }
            
            if( isset($_POST['title']) AND isset($_POST['description']) AND isset($_POST['human']) )
            {
               $this->story_edition->title = $_POST['title'];
               $this->story_edition->description = $_POST['description'];
               
               if( !isset($_POST['media_id']) )
                  $this->story_edition->media_id = NULL;
               else if($_POST['media_id'] == 'none' OR isset($_POST['noimages']) )
                  $this->story_edition->media_id = NULL;
               else
                  $this->story_edition->media_id = $_POST['media_id'];
               
               /// otra comprobación más para evitar el spam
               if( strstr($_POST['description'], '<a href=') )
                  $this->new_error_msg('De eso nada, aquí no se permite HTML.');
               else if( $_POST['human'] != 'POZI' )
                  $this->new_error_msg('Has contestado que no eres humano, y si no eres
                     humano no puedes editar historias. Y si, ya sé que esto es nazismo puro,
                     pero es una forma sencilla de atajar el SPAM.');
               else
               {
                  $this->story_edition->save();
                  $this->new_message('Historia editada correctamente. Hac clic <a href="'.
                     $this->story_edition->url().'">aquí</a> para verla. Recuerda que
                        aparecerá en la sección <a href="'.FS_PATH.'/index.php?page=last_editions">ediciones</a>.');
                  
                  if($_POST['masterkey'] == FS_MASTER_KEY AND FS_MASTER_KEY != '')
                  {
                     $this->story->title = $this->story_edition->title;
                     $this->story->description = $this->story_edition->description;
                     $this->story->native_lang = TRUE;
                     
                     if( isset($_POST['noimages']) )
                     {
                        $this->story->noimages = TRUE;
                        $this->story->media_id = NULL;
                        
                        $sm = new story_media();
                        foreach($sm->all4story($this->story->get_id()) as $sm0)
                        {
                           $sm0->delete();
                           $mi = $sm0->media_item();
                           if($mi)
                              $mi->delete();
                        }
                     }
                     else
                        $this->story->noimages = FALSE;
                     
                     $this->story->keywords = strtolower($_POST['keywords']);
                     
                     if( substr($_POST['related'], 0, 7) == 'http://' )
                     {
                        $aux = explode('/', $_POST['related']);
                        $related = $this->story->get($aux[ count($aux)-1 ]);
                        if($related)
                           $this->story->related_id = $related->get_id();
                     }
                     else if( isset($_POST['norelated']) )
                     {
                        $this->story->related_id = NULL;
                     }
                     else if( !isset($this->story->related_id) AND $this->story->keywords != '' )
                     {
                        $aux = explode(',', $this->story->keywords);
                        $keyword = trim($aux[0]);
                        $relateds = $this->story->search($keyword);
                        
                        for($i = 0; $i < count($relateds); $i++)
                        {
                           if( !isset($this->story->related_id) AND $relateds[$i]->date < $this->story->date AND $relateds[$i]->native_lang )
                              $this->story->related_id = $relateds[$i]->get_id();
                           
                           if( $relateds[$i]->get_id() != $this->story->get_id() )
                           {
                              $relateds[$i]->add_keyword($keyword);
                              
                              for($j = 0; $j < count($relateds); $j++)
                              {
                                 if( !isset($relateds[$i]->related_id) AND $relateds[$j]->date < $relateds[$i]->date AND $relateds[$j]->native_lang )
                                 {
                                    $relateds[$i]->related_id = $relateds[$j]->get_id();
                                    break;
                                 }
                              }
                              
                              $relateds[$i]->save();
                           }
                        }
                     }
                     
                     $this->story->save();
                  }
                  else if($_POST['masterkey'])
                     $this->new_error_msg('Contraseña incorrecta.');
                  
                  $this->select_best_image4story();
                  
                  /// ¿La noticia está en otro idioma?
                  if( !$this->story->native_lang )
                  {
                     $this->story->title = $this->story_edition->title;
                     $this->story->description = $this->story_edition->description;
                     $this->story->native_lang = TRUE;
                     $this->story->save();
                  }
                  
                  /// actualizamos al visitante
                  $this->visitor->human = TRUE;
                  $this->visitor->need_save = TRUE;
                  $this->visitor->save();
               }
            }
            
            $sv0 = $this->story_visit->get_by_params($this->story->get_id(), $_SERVER['REMOTE_ADDR']);
            if( $sv0 )
            {
               $sv0->edition_id = $this->story_edition->get_id();
               $sv0->save();
            }
            else
            {
               $this->story_visit->story_id = $this->story->get_id();
               $this->story_visit->edition_id = $this->story_edition->get_id();
               $this->story_visit->save();
               $this->story->clics++;
               $this->story->save();
            }
         }
      }
      else
         $this->new_error_msg('Historia no encontrada.');
   }
   
   public function url()
   {
      if( $this->story )
         return $this->story->edit_url();
      else
         return parent::url();
   }
   
   public function get_description()
   {
      if( $this->story )
         return $this->story->description();
      else
         return parent::get_description();
   }
   
   private function select_best_image4story()
   {
      if( !$this->story->noimages )
      {
         /// Elegimos la foto de la edición más votada de esta historia
         $maxvotes = 0;
         foreach($this->story->editions() as $edi)
         {
            if($edi->votes > $maxvotes)
            {
               $maxvotes = $edi->votes;
               $this->story->media_id = $edi->media_id;
            }
         }
         $this->story->save();
      }
   }
}

?>