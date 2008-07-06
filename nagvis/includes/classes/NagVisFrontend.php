<?php
/*****************************************************************************
 *
 * NagVisFrontend.php - Class for handling the NagVis frontend
 *
 * Copyright (c) 2004-2008 NagVis Project (Contact: lars@vertical-visions.de)
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
class NagVisFrontend extends GlobalPage {
	var $MAINCFG;
	var $MAPCFG;
	var $BACKEND;
	var $LANG;
	
	var $MAP;
	
	var $headerTemplate;
	var $htmlBase;
	
	/**
	 * Class Constructor
	 *
	 * @param 	GlobalMainCfg 	$MAINCFG
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function NagVisFrontend(&$MAINCFG,&$MAPCFG = '',&$BACKEND = '') {
		$prop = Array();
		
		$this->MAINCFG = &$MAINCFG;
		$this->MAPCFG = &$MAPCFG;
		$this->BACKEND = &$BACKEND;
		
		$this->LANG = new GlobalLanguage($MAINCFG, 'nagvis');
		
		$this->htmlBase = $this->MAINCFG->getValue('paths','htmlbase');
		
		$prop['title'] = $MAINCFG->getValue('internal', 'title');
		$prop['cssIncludes'] = Array($this->htmlBase.'/nagvis/includes/css/style.css');
		$prop['jsIncludes'] = Array($this->htmlBase.'/nagvis/includes/js/nagvis.js', $this->htmlBase.'/nagvis/includes/js/overlib.js', $this->htmlBase.'/nagvis/includes/js/dynfavicon.js', $this->htmlBase.'/nagvis/includes/js/ajax.js', $this->htmlBase.'/nagvis/includes/js/hover.js');
		$prop['extHeader'] = '<link rel="shortcut icon" href="'.$this->htmlBase.'/nagvis/images/internal/favicon.png">';
		$prop['languageRoot'] = 'nagvis';
		
		// Only do this, when a map needs to be displayed
		if($this->MAPCFG != '') {
			$this->headerTemplate = $this->MAPCFG->getValue('global', 0, 'header_template');
			
			$prop['extHeader'] .= '<style type="text/css">body.main { background-color: '.$this->MAPCFG->getValue('global',0, 'background_color').'; }</style>';
			$prop['allowedUsers'] = $this->MAPCFG->getValue('global',0, 'allowed_user');
		} else {
			$this->headerTemplate = $this->MAINCFG->getValue('defaults', 'headertemplate');
		}
		
		parent::GlobalPage($this->MAINCFG,$prop);
	}
	
	/**
	 * Displays the automatic index page of all maps
	 *
	 * @return	Array   HTML Code of Index Page
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getIndexPage() {
			$ret = '';
			
			$ret .= '<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>';
			$ret .= '<div class="infopage">';
			$ret .= '<table>';
			$ret .= '<tr><th colspan="4">'.$this->LANG->getText('mapIndex').'</th></tr><tr>';
			$i = 1;
			foreach($this->getMaps() AS $mapName) {
				$MAPCFG = new NagVisMapCfg($this->MAINCFG,$mapName);
				$MAPCFG->readMapConfig();
				
				if($MAPCFG->getValue('global',0, 'show_in_lists') == 1 && ($mapName != '__automap' || ($mapName == '__automap' && $this->MAINCFG->getValue('automap', 'showinlists')))) {
					if($mapName == '__automap') {
						$opts = Array();
						
						// Fetch option array from defaultparams string (extract variable 
						// names and values)
						$params = explode('&',$this->MAINCFG->getValue('automap','defaultparams'));
						unset($params[0]);
						
						foreach($params AS &$set) {
							$arrSet = explode('=',$set);
							$opts[$arrSet[0]] = $arrSet[1];
						}
						
						$opts['preview'] = 1;
						
						$MAP = new NagVisAutoMap($this->MAINCFG, $this->LANG, $this->BACKEND, $opts);
						// If there is no automap image on first load of the index page,
						// render the image
						$MAP->renderMap();
					} else {
						$MAP = new NagVisMap($this->MAINCFG, $MAPCFG, $this->LANG, $this->BACKEND);
					}
					$MAP->MAPOBJ->fetchIcon();
					
					// Check if the user is permited to view this map
					if($MAP->MAPOBJ->checkPermissions($MAPCFG->getValue('global',0, 'allowed_user'),FALSE)) {
						if($MAP->MAPOBJ->checkMaintenance(0)) {
							$class = '';
							
							if($mapName == '__automap') {
								$onClick = 'location.href=\''.$this->htmlBase.'/index.php?automap=1'.$this->MAINCFG->getValue('automap','defaultparams').'\';';
							} else {
								$onClick = 'location.href=\''.$this->htmlBase.'/index.php?map='.$mapName.'\';';
							}
							
							$summaryOutput = $MAP->MAPOBJ->getSummaryOutput();
						} else {
							$class = 'class="disabled"';
							
							$onClick = 'alert(\''.$this->LANG->getText('mapInMaintenance').'\');';
							$summaryOutput = $this->LANG->getText('mapInMaintenance');
						}
						
						// If this is the automap display the last rendered image
						if($mapName == '__automap') {
							$imgPath = $this->MAINCFG->getValue('paths','var').'automap.png';
							$imgPathHtml = $this->MAINCFG->getValue('paths','htmlvar').'automap.png';
						} else {
							$imgPath = $this->MAINCFG->getValue('paths','map').$MAPCFG->BACKGROUND->getFileName();
							$imgPathHtml = $this->MAINCFG->getValue('paths','htmlmap').$MAPCFG->BACKGROUND->getFileName();
						}
						
						// Now form the cell with it's contents
						$ret .= '<td '.$class.' style="width:200px;height:200px;" onMouseOut="this.style.cursor=\'auto\';this.bgColor=\'\';return nd();" onMouseOver="this.style.cursor=\'pointer\';this.bgColor=\'#ffffff\';return overlib(\'<table class=\\\'infopage_hover_table\\\'><tr><td>'.strtr(addslashes($summaryOutput),Array('"' => '\'', "\r" => '', "\n" => '')).'</td></tr></table>\');" onClick="'.$onClick.'">';
						$ret .= '<img align="right" src="'.$MAP->MAPOBJ->iconHtmlPath.$MAP->MAPOBJ->icon.'" />';
						$ret .= '<h2>'.$MAPCFG->getValue('global', '0', 'alias').'</h2><br />';
						if($MAPCFG->getValue('global', 0,'usegdlibs') == '1' && $MAP->checkGd(1)) {
							$ret .= '<img style="width:200px;height:150px;" src="'.$this->createThumbnail($imgPath, $mapName).'" /><br />';
						} else {
							$ret .= '<img style="width:200px;height:150px;" src="'.$imgPathHtml.'" /><br />';
						}
						$ret .= '</td>';
						if($i % 4 == 0) {
								$ret .= '</tr><tr>';
						}
						$i++;
					}
				}
			}
			// Fill table with empty cells if there are not enough maps to get the line filled
			if(($i - 1) % 4 != 0) {
					for($a=0;$a < (4 - (($i - 1) % 4));$a++) {
							$ret .= '<td>&nbsp;</td>';
					}
			}
			$ret .= '</tr>';
			$ret .= '</table>';
			
			/**
			 * Infobox lists all map rotation pools
			 */
			$aRotationPools = $this->getRotationPools();
			if(count($aRotationPools) > 0) {
				$ret .= '<table class="infobox">';
				$ret .= '<tr><th>'.$this->LANG->getText('rotationPools').'</th></tr>';
				foreach($this->getRotationPools() AS $poolName) {
					// Form the onClick action
					$onClick = 'location.href=\''.$this->htmlBase.'/index.php?rotation='.$poolName.'\';';
					
					// Now form the HTML code for the cell
					$ret .= '<tr><td onMouseOut="this.style.cursor=\'auto\';this.bgColor=\'\';return nd();" onMouseOver="this.style.cursor=\'pointer\';this.bgColor=\'#ffffff\';" onClick="'.$onClick.'">';
					$ret .= '<h2>'.$poolName.'</h2><br />';
					$ret .= '</td>';
					$ret .= '</tr>';
				}
				$ret .= '</table>';
				$ret .= '</div>';
			}
			
			return $ret;
	}
	
	/**
	 * Creates thumbnail images for the index map
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function createThumbnail($imgPath, $mapName) {
		if($this->checkVarFolderWriteable(TRUE) && $this->checkImageExists($imgPath, TRUE)) {
			$imgSize = getimagesize($imgPath);
			// 0: width, 1:height, 2:type
			
			switch($imgSize[2]) {
				case 1: // GIF
				$image = imagecreatefromgif($imgPath);
				break;
				case 2: // JPEG
				$image = imagecreatefromjpeg($imgPath);
				break;
				case 3: // PNG
				$image = imagecreatefrompng($imgPath);
				break;
				default:
					$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:global'));
					$FRONTEND->messageToUser('ERROR','onlyPngOrJpgImages');
				break;
			}
			
			// Maximum size
			$thumbMaxWidth = 200;
			$thumbMaxHeight = 150;
			
			$thumbWidth = $imgSize[0];
			$thumbHeight = $imgSize[1];
			
			if ($thumbWidth > $thumbMaxWidth) {
				$factor = $thumbMaxWidth / $thumbWidth;
				$thumbWidth *= $factor;
				$thumbHeight *= $factor;
			}
			
			if ($thumbHeight > $thumbMaxHeight) {
				$factor = $thumbMaxHeight / $thumbHeight;
				$thumbWidth *= $factor;
				$thumbHeight *= $factor;
			}
			
			$thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
			imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $imgSize[0], $imgSize[1]);
			
			imagepng($thumb, $this->MAINCFG->getValue('paths','var').$mapName.'-thumb.png');
			
			return $this->MAINCFG->getValue('paths','htmlvar').$mapName.'-thumb.png';
		} else {
			return '';
		}
	}
	
	/**
	 * Checks for writeable VarFolder
	 *
	 * @param		Boolean 	$printErr
	 * @return	Boolean		Is Successful?
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function checkVarFolderExists($printErr) {
		if(file_exists(substr($this->MAINCFG->getValue('paths', 'var'),0,-1))) {
			return TRUE;
		} else {
			if($printErr == 1) {
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:global'));
				$FRONTEND->messageToUser('ERROR','varFolderNotExists','PATH~'.$this->MAINCFG->getValue('paths', 'var'));
			}
			return FALSE;
		}
	}
	
	/**
	 * Checks for writeable VarFolder
	 *
	 * @param		Boolean 	$printErr
	 * @return	Boolean		Is Successful?
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function checkVarFolderWriteable($printErr) {
		if($this->checkVarFolderExists($printErr) && is_writable(substr($this->MAINCFG->getValue('paths', 'var'),0,-1)) && @file_exists($this->MAINCFG->getValue('paths', 'var').'.')) {
			return TRUE;
		} else {
			if($printErr == 1) {
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:global'));
				$FRONTEND->messageToUser('ERROR','varFolderNotWriteable','PATH~'.$this->MAINCFG->getValue('paths', 'var'));
			}
			return FALSE;
		}
	}
	
	/**
	 * Checks Image exists
	 *
	 * @param 	String	$imgPath
	 * @param 	Boolean	$printErr
	 * @return	Boolean	Is Check Successful?
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function checkImageExists($imgPath, $printErr) {
		if(file_exists($imgPath)) {
			return TRUE;
		} else {
			if($printErr == 1) {
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:global'));
				$FRONTEND->messageToUser('WARNING','imageNotExists','FILE~'.$imgPath);
			}
			return FALSE;
		}
	}
	
	/**
	 * If enabled, the header menu is added to the page
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getHeaderMenu() {
		if($this->MAINCFG->getValue('global', 'displayheader') == '1') {
			if($this->checkHeaderTemplateReadable(1)) {
				$ret = file_get_contents($this->MAINCFG->getValue('paths','headertemplate').'tmpl.'.$this->headerTemplate.'.html');
				
				// Replace some macros
				if($this->MAPCFG != '') {
					$ret = str_replace('[current_map]',$this->MAPCFG->getName(),$ret);
					$ret = str_replace('[current_map_alias]',$this->MAPCFG->getValue('global', '0', 'alias'),$ret);
				}
				$ret = str_replace('[html_base]',$this->htmlBase,$ret);
				$ret = str_replace('[html_templates]',$this->MAINCFG->getValue('paths','htmlheadertemplates'),$ret);
				$ret = str_replace('[html_template_images]',$this->MAINCFG->getValue('paths','htmlheadertemplateimages'),$ret);
				// Replace language macros
				$ret = str_replace('[lang_select_map]',$this->LANG->getText('selectMap'),$ret);
				$ret = str_replace('[lang_edit_map]',$this->LANG->getText('editMap'),$ret);
				$ret = str_replace('[lang_need_help]',$this->LANG->getText('needHelp'),$ret);
				$ret = str_replace('[lang_online_doc]',$this->LANG->getText('onlineDoc'),$ret);
				$ret = str_replace('[lang_forum]',$this->LANG->getText('forum'),$ret);
				$ret = str_replace('[lang_support_info]',$this->LANG->getText('supportInfo'),$ret);
				$ret = str_replace('[lang_overview]',$this->LANG->getText('overview'),$ret);
				$ret = str_replace('[lang_instance]',$this->LANG->getText('instance'),$ret);
				$ret = str_replace('[lang_rotation_start]','<br />'.$this->LANG->getText('rotationStart'),$ret);
				$ret = str_replace('[lang_rotation_stop]','<br />'.$this->LANG->getText('rotationStop'),$ret);
				$ret = str_replace('[lang_refresh_start]',$this->LANG->getText('refreshStart'),$ret);
				$ret = str_replace('[lang_refresh_stop]',$this->LANG->getText('refreshStop'),$ret);
				// Replace lists
				if(preg_match_all('/<!-- BEGIN (\w+) -->/',$ret,$matchReturn) > 0) {
					foreach($matchReturn[1] AS &$key) {
						if($key == 'maplist') {
							$sReplace = '';
							preg_match_all('/<!-- BEGIN '.$key.' -->((?s).*)<!-- END '.$key.' -->/',$ret,$matchReturn1);
							foreach($this->getMaps() AS $mapName) {
								$MAPCFG1 = new NagVisMapCfg($this->MAINCFG,$mapName);
								$MAPCFG1->readMapConfig(1);
								
								if($MAPCFG1->getValue('global',0, 'show_in_lists') == 1 && ($mapName != '__automap' || ($mapName == '__automap' && $this->MAINCFG->getValue('automap', 'showinlists')))) {
									$sReplaceObj = str_replace('[map_name]',$MAPCFG1->getName(),$matchReturn1[1][0]);
									$sReplaceObj = str_replace('[map_alias]',$MAPCFG1->getValue('global', '0', 'alias'),$sReplaceObj);
									
									// Add defaultparams to map selection
									if($mapName == '__automap') {
										$sReplaceObj = str_replace('[url_params]', $this->MAINCFG->getValue('automap', 'defaultparams'), $sReplaceObj);
									} else {
										$sReplaceObj = str_replace('[url_params]','',$sReplaceObj);
									}
									
									// auto select current map
									if($this->MAPCFG != '' && $mapName == $this->MAPCFG->getName() || ($mapName == '__automap' && isset($_GET['automap']))) {
										$sReplaceObj = str_replace('[selected]','selected="selected"',$sReplaceObj);
									} else {
										$sReplaceObj = str_replace('[selected]','',$sReplaceObj);
									}
									
									$sReplace .= $sReplaceObj;
								}
							}
							$ret = preg_replace('/<!-- BEGIN '.$key.' -->(?:(?s).*)<!-- END '.$key.' -->/',$sReplace,$ret);
						}
					}
				}
				
				$this->addBodyLines('<div class="header">'.$ret.'</div>');
			}
		}
	}
	
	/**
	 * Checks for existing header template
	 *
	 * @param 	Boolean	$printErr
	 * @return	Boolean	Is Check Successful?
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function checkHeaderTemplateExists($printErr) {
		if(file_exists($this->MAINCFG->getValue('paths', 'headertemplate').'tmpl.'.$this->headerTemplate.'.html')) {
			return TRUE;
		} else {
			if($printErr == 1) {
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'global:global'));
				$FRONTEND->messageToUser('WARNING','headerTemplateNotExists','FILE~'.$this->MAINCFG->getValue('paths', 'headertemplate').'tmpl.'.$this->headerTemplate.'.html');
			}
			return FALSE;
		}
	}
	
	/**
	 * Checks for readable header template
	 *
	 * @param 	Boolean	$printErr
	 * @return	Boolean	Is Check Successful?
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function checkHeaderTemplateReadable($printErr) {
		if($this->checkHeaderTemplateExists($printErr) && is_readable($this->MAINCFG->getValue('paths', 'headertemplate').'tmpl.'.$this->headerTemplate.'.html')) {
			return TRUE;
		} else {
			if($printErr == 1) {
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'global:global'));
				$FRONTEND->messageToUser('WARNING','headerTemplateNotReadable','FILE~'.$this->MAINCFG->getValue('paths', 'headertemplate').'tmpl.'.$this->headerTemplate.'.html');
			}
			return FALSE;
		}
	}
	
	/**
	 * Adds the map to the page
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getMap() {
		$this->addBodyLines('<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>');
		$this->addBodyLines('<div class="map">');
		$this->MAP = new NagVisMap($this->MAINCFG,$this->MAPCFG,$this->LANG,$this->BACKEND);
		$this->MAP->MAPOBJ->checkMaintenance(1);
		$this->addBodyLines($this->MAP->parseMap());
		$this->addBodyLines('</div>');
	}
	
	/**
	 * Adds the automap to the page
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getAutoMap($arrOptions) {
		$this->addBodyLines('<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>');
		$this->addBodyLines('<div id="map" class="map">');
		$this->MAP = new NagVisAutoMap($this->MAINCFG, $this->LANG, $this->BACKEND, $arrOptions);
		$this->addBodyLines($this->MAP->parseMap());
		$this->addBodyLines('</div>');
	}
	
	/**
	 * Adds the user messages to the page
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getMessages() {
		$this->addBodyLines($this->getUserMessages());
	}

	/**
	 * Gets the javascript code for the map refresh/rotation
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getRefresh() {
		$strReturn = "";
		if(isset($_GET['rotation']) && $_GET['rotation'] != '' && (!isset($_GET['rotate']) || (isset($_GET['rotate']) && $_GET['rotate'] == '1'))) {
			$strReturn .= "var rotate = true;\n";
		} else {
			$strReturn .= "var rotate = false;\n";
		}
		$strReturn .= "var bRefresh = true;\n";
		$strReturn .= "var nextRotationUrl = '".$this->getNextRotationUrl()."';\n";
		$strReturn .= "var nextRefreshTime = '".$this->getNextRotationTime()."';\n";
		$strReturn .= "var oRotation = window.setTimeout('countdown()', 1000);\n";
		
	    return $this->parseJs($strReturn);
	}
	
	/**
	 * Returns the next time to refresh or rotate in seconds
	 *
	 * @return	Integer		Returns The next rotation time in seconds
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getNextRotationTime() {
		if(isset($_GET['rotation']) && $_GET['rotation'] != '') {
			return $this->MAINCFG->getValue('rotation_'.$_GET['rotation'], 'interval');
		} else {
			return $this->MAINCFG->getValue('rotation', 'interval');
		}
	}
  
	/**
	 * Gets the Next map to rotate to, if enabled
	 * If Next map is in [ ], it will be an absolute url
	 *
	 * @return	String  URL to rotate to
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getNextRotationUrl() {
		if(isset($_GET['rotation']) && $_GET['rotation'] != '') {
			if($maps = $this->MAINCFG->getValue('rotation_'.$_GET['rotation'], 'maps')) {
				$maps = explode(',', str_replace('"','',$maps));
				
				if(isset($_GET['url']) && $_GET['url'] != '') {
					$currentMap = '['.$_GET['url'].']';
				} else {
					$currentMap = $this->MAPCFG->getName();
				}
			
				// get position of actual map in the array
				$index = array_search($currentMap,$maps);
				if(($index + 1) >= sizeof($maps)) {
					// if end of array reached, go to the beginning...
					$index = 0;
				} else {
					$index++;
				}
					
				$nextMap = $maps[$index];
				
				
				if(preg_match("/^\[(.+)\]$/",$nextMap,$arrRet)) {
					return 'index.php?rotation='.$_GET['rotation'].'&url='.$arrRet[1];
				} else {
					return 'index.php?rotation='.$_GET['rotation'].'&map='.$nextMap;
				}
			} else {
				// Error Message (Map rotation pool does not exist)
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:global'));
				$FRONTEND->messageToUser('ERROR','mapRotationPoolNotExists','ROTATION~'.$_GET['rotation']);
				
				return '';
			}
		} else {
			return '';
		}
	}
	
	/**
	 * Gets all defined maps
	 *
	 * @return	Array maps
	 * @author Lars Michelsen <lars@vertical-visions.de>
	 */
	function getMaps() {
		$files = Array();
		
		if ($handle = opendir($this->MAINCFG->getValue('paths', 'mapcfg'))) {
 			while (false !== ($file = readdir($handle))) {
				if(preg_match('/^.+\.cfg$/', $file)) {
					$files[] = substr($file,0,strlen($file)-4);
				}
			}
			
			if ($files) {
				natcasesort($files);
			}
		}
		closedir($handle);
		
		return $files;
	}
	
	/**
	 * Gets all rotation pools
	 *
	 * @return	Array pools
	 * @author Lars Michelsen <lars@vertical-visions.de>
	 */
	function getRotationPools() {
		$ret = Array();
		
		foreach($this->MAINCFG->config AS $sec => &$var) {
			if(preg_match('/^rotation_/i', $sec)) {
				$ret[] = $var['rotationid'];
			}
		}
		
		return $ret;
	}
}
?>
