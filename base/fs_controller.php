<?php
/*
 * This file is part of FeedStorm
 * Copyright (C) 2012  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'model/feed.php';
require_once 'model/story.php';
require_once 'model/visitor.php';

class fs_controller
{
   private $uptime;
   public $error_msg;
   public $message;
   public $page;
   public $title;
   private $admin_page;
   public $template;
   public $visitor;
   
   public function __construct($name='not_found', $title='home', $admin=FALSE)
   {
      $tiempo = explode(' ', microtime());
      $this->uptime = $tiempo[1] + $tiempo[0];
      $this->page = $name;
      $this->title = $title;
      $this->admin_page = $admin;
      $this->template = $name;
      $this->error_msg = FALSE;
      $this->message = FALSE;
      
      if( isset($_COOKIE['key']) )
      {
         $this->visitor = new visitor($_COOKIE['key']);
      }
      else
      {
         $this->visitor = new visitor();
         setcookie('key', $this->visitor->key, time()+31536000);
      }
      
      $this->process();
   }
   
   public function new_error_msg($msg)
   {
      if( !$this->error_msg )
         $this->error_msg = $msg;
      else
         $this->error_msg .= "<br/>" . $msg;
   }
   
   public function new_message($msg)
   {
      if( !$this->message )
         $this->message = $msg;
      else
         $this->message .= '<br/>' . $msg;
   }
   
   public function duration()
   {
      $tiempo = explode(" ", microtime());
      return (number_format($tiempo[1] + $tiempo[0] - $this->uptime, 3) . ' s');
   }
   
   protected function process()
   {
      
   }
   
   public function is_admin_page()
   {
      return $this->admin_page;
   }
   
   public function url()
   {
      return 'index.php?page='.$this->page;
   }
}

?>