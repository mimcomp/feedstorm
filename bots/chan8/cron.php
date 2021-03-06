<?php
/*
 * This file is part of FeedStorm
 * Copyright (C) 2014  Carlos Garcia Gomez  neorazorx@gmail.com
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

/**
 * Busca imágenes y videos en los enlaces de los artículos.
 * @param type $story
 */
function chan8(&$story)
{
   $story_preview = new story_preview();
   
   foreach($story->popular_stories( mt_rand(10, 100) ) as $sto)
   {
      echo '.';
      
      $story_preview->load($sto->link, $sto->description_uncut());
      if( !$story_preview->type AND mb_strlen($sto->description) < 500 AND $sto->num_editions == 0 AND empty($sto->topics) )
      {
         $html = $story_preview->curl_download($sto->link);
         $urls = array();
         if( preg_match_all('@<meta property="og:image" content="([^"]+)@', $html, $urls) )
         {
            foreach($urls[1] as $url)
            {
               $story_preview->load($url);
               if($story_preview->type AND stripos($url, 'logo') === FALSE)
               {
                  $sto->description .= ' '.$story_preview->link;
                  $sto->save();
                  echo '(img)';
                  break;
               }
            }
         }
         
         if( !$story_preview->type AND mb_strlen($sto->description) < 200 AND count($sto->topics) == 0 )
         {
            /// buscamos vídeos de youtube
            $urls = array();
            if( preg_match_all('@((https?://)?([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*)@', $html, $urls) )
            {
               foreach($urls[0] as $url)
               {
                  foreach( array('youtube', 'youtu.be', 'vimeo') as $domain )
                  {
                     if( strpos($url, $domain) !== FALSE )
                     {
                        $story_preview->load($url);
                        if( in_array($story_preview->type, array('youtube', 'vimeo')) )
                        {
                           $sto->description .= ' '.$story_preview->link;
                           $sto->save();
                           echo '('.$domain.')';
                           break;
                        }
                     }
                  }
                  
                  if($story_preview->type)
                     break;
               }
            }
         }
      }
      
      $story_preview->set_downloads(5);
   }
}

chan8($story);