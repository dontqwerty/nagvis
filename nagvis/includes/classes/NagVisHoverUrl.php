<?php
/*****************************************************************************
 *
 * NagVisHoverUrl.php - Class for handling the hover urls
 *
 * Copyright (c) 2004-2008 NagVis Project
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/
 
/**
 * @author	Lars Michelsen <lars@vertical-visions.de>
 */
class NagVisHoverUrl {
	private $CORE;
	
	private $url;
	private $code;
	
	/**
	 * Class Constructor
	 *
	 * @param 	GlobalCore 	$CORE
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	public function __construct(&$CORE, $url) {
		$this->CORE = &$CORE;
		$this->url = $url;
    
    $this->code = '';
		
		// Read the contents of the template file
		$this->readHoverUrl();
		$this->cleanCode();
	}
  
  /**
	 * PUBLIC __toString()
	 *
	 * "Magic method" returns the contents of the hover url
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
  public function __toString() {
    return $this->code;
  }
  
  /**
	 * PRIVATE readHoverUrl()
	 *
	 * Reads the given hover url form an object and forms it to a readable format for the hover box
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	private function readHoverUrl() {
		/* FIXME: Context is supported in php >= 5.0
		* $http_opts = array(
		*      'http'=>array(
		*      'method'=>"GET",
		*      'header'=>"Accept-language: en\r\n" .
		*                "Authorization: Basic ".base64_encode("user:pw"),
		*      'request_fulluri'=>true  ,
		*      'proxy'=>"tcp://proxy.url.de"
		*   )
		* );
		* $context = stream_context_create($http_opts);
		* $content = file_get_contents($obj['hover_url'],FALSE,$context);
		*/
    
		if(!$content = file_get_contents($this->url)) {
			new GlobalFrontendMessage('WARNING', $this->CORE->LANG->getText('couldNotGetHoverUrl', 'URL~'.$this->url));
		}
    
    $this->code = $content;
	}
	
  
	/**
	 * PRIVATE cleanCode()
	 *
	 * Replace unwanted things from the code
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
  private function cleanCode() {
		$this->code = str_replace('"','\\\'',str_replace('\'','\\\'',str_replace("\t",'',str_replace("\n",'',str_replace("\r\n",'',$this->code)))));
  }
}
?>
