<?php
/*****************************************************************************
 *
 * WuiBackendManagement.php - Class for managing backends in WUI
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
class WuiBackendManagement extends GlobalPage {
	var $CORE;
	var $MAINCFG;
	var $LANG;
	
	var $DEFBACKENDFORM;
	var $ADDBACKENDFORM;
	
	var $propCount;
	
	/**
	 * Class Constructor
	 *
	 * @param 	GlobalMainCfg 	$CORE
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function WuiBackendManagement(&$CORE) {
		$this->CORE = &$CORE;
		$this->MAINCFG = &$CORE->MAINCFG;
		$this->LANG = &$CORE->LANG;
		
		$this->propCount = 0;
		
		$prop = Array('title' => $this->CORE->MAINCFG->getValue('internal', 'title'),
					  'cssIncludes'=>Array('./includes/css/wui.css'),
					  'jsIncludes'=>Array('../nagvis/includes/js/ajax.js','./includes/js/wui.js',
					  						'./includes/js/BackendManagement.js',
					  						'./includes/js/ajax.js'),
					  'extHeader'=> '',
					  'allowedUsers' => $this->CORE->MAINCFG->getValue('wui','allowedforconfig'),
					  'languageRoot' => 'nagvis');
		parent::__construct($CORE, $prop);
	}
	
	/**
	* If enabled, the form is added to the page
	*
	* @author Lars Michelsen <lars@vertical-visions.de>
	*/
	function getForm() {
		// Inititalize language for JS
		$this->addBodyLines($this->parseJs($this->getJsLang()));
		
		$this->DEFBACKENDFORM = new GlobalForm(Array('name'=>'backend_default',
			'id'=>'backend_default',
			'method'=>'POST',
			'action'=>'./form_handler.php?myaction=mgt_backend_default',
			'onSubmit'=>'return update_param(\'backend_default\');',
			'cols'=>'2'));
		$this->addBodyLines($this->DEFBACKENDFORM->initForm());
		$this->addBodyLines($this->DEFBACKENDFORM->getCatLine(strtoupper($this->LANG->getText('setDefaultBackend'))));
		$this->propCount++;
		$this->addBodyLines($this->getDefaultFields());
		$this->addBodyLines($this->DEFBACKENDFORM->getSubmitLine($this->LANG->getText('save')));
		$this->addBodyLines($this->DEFBACKENDFORM->closeForm());
		
		$this->ADDBACKENDFORM = new GlobalForm(Array('name'=>'backend_add',
			'id'=>'backend_add',
			'method'=>'POST',
			'action'=>'./form_handler.php?myaction=mgt_backend_add',
			'onSubmit'=>'return check_backend_add();',
			'cols'=>'2'));
		$this->addBodyLines($this->ADDBACKENDFORM->initForm());
		$this->addBodyLines($this->ADDBACKENDFORM->getCatLine(strtoupper($this->LANG->getText('addBackend'))));
		$this->propCount++;
		$this->addBodyLines($this->getAddFields());
		$this->addBodyLines($this->ADDBACKENDFORM->getSubmitLine($this->LANG->getText('save')));
		$this->addBodyLines($this->ADDBACKENDFORM->closeForm());
		
		$this->EDITBACKENDFORM = new GlobalForm(Array('name'=>'backend_edit',
			'id'=>'backend_edit',
			'method'=>'POST',
			'action'=>'./form_handler.php?myaction=mgt_backend_edit',
			'onSubmit'=>'return check_backend_edit();',
			'cols'=>'2'));
		$this->addBodyLines($this->EDITBACKENDFORM->initForm());
		$this->addBodyLines($this->EDITBACKENDFORM->getCatLine(strtoupper($this->LANG->getText('editBackend'))));
		$this->propCount++;
		$this->addBodyLines($this->getEditFields());
		$this->addBodyLines($this->EDITBACKENDFORM->getSubmitLine($this->LANG->getText('save')));
		$this->addBodyLines($this->EDITBACKENDFORM->closeForm());
		
		$this->DELBACKENDFORM = new GlobalForm(Array('name'=>'backend_del',
			'id'=>'backend_del',
			'method'=>'POST',
			'action'=>'./form_handler.php?myaction=mgt_backend_del',
			'onSubmit'=>'return check_backend_del();',
			'cols'=>'2'));
		$this->addBodyLines($this->DELBACKENDFORM->initForm());
		$this->addBodyLines($this->DELBACKENDFORM->getCatLine(strtoupper($this->LANG->getText('delBackend'))));
		$this->propCount++;
		$this->addBodyLines($this->getDelFields());
		$this->addBodyLines($this->DELBACKENDFORM->getSubmitLine($this->LANG->getText('save')));
		$this->addBodyLines($this->DELBACKENDFORM->closeForm());
		
		// Resize the window
		$this->addBodyLines($this->parseJs($this->resizeWindow(540,$this->propCount*35+180)));
	}
	
	/**
	 * Gets edit fields of the form
	 *
	 * @return	Array	HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
	function getEditFields() {
		$ret = Array();
		$ret = array_merge($ret,$this->EDITBACKENDFORM->getSelectLine('backend_id','backend_id',array_merge(Array(''=>''),$this->CORE->getDefinedBackends()),'',TRUE,"getBackendOptions('',this.value,'".$this->EDITBACKENDFORM->getId()."');"));
		$this->propCount++;
		$ret[] = "<script language=\"javascript\">";
		$ret[] = "\tvar backendOptions = Array();";
		$a = $this->MAINCFG->getValidObjectType('backend');
		foreach($a['options'] AS $backendtype => $arr) {
			$ret[] = "\tbackendOptions['".$backendtype."'] = Array();";
			foreach($arr AS $key => $opt) {
				$ret[] = "\tbackendOptions['".$backendtype."']['".$key."'] = Array();";
				foreach($opt AS $var => $val) {
					$ret[] = "\tbackendOptions['".$backendtype."']['".$key."']['".$var."'] = '".$val."'";
				}
			}
		}
		$ret[] = "\tvar definedBackends = Array();";
		$ret[] = "\tdefinedBackends['-'] = Array();";
		foreach($this->MAINCFG->getConfig() AS $sec => $arr) {
			if(preg_match("/^backend_/i", $sec)) {
				$backend_id = preg_replace("/^backend_/i",'',$sec);
				$ret[] = "\tdefinedBackends['".$backend_id."'] = Array();";
				foreach($arr AS $key => $val) {
					if(!preg_match("/^comment_/i", $key)) {
						$ret[] = "\tdefinedBackends['".$backend_id."']['".$key."'] = '".$val."';";
					}
				}
			}
		}
		$ret[] = "</script>";
		return $ret;
	}
	
	/**
	 * Gets delete fields of the form
	 *
	 * @return	Array	HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
	function getDelFields() {
		$ret = Array();
		$this->propCount++;
		$ret = array_merge($ret,$this->DELBACKENDFORM->getSelectLine('backend_id','backend_id',array_merge(Array(''=>''),$this->CORE->getDefinedBackends()),'',TRUE));
		
		return $ret;
	}
	
	/**
	 * Gets add fields of the form
	 *
	 * @return	Array	HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
	function getAddFields() {
		$ret = Array();
		$ret = array_merge($ret,$this->ADDBACKENDFORM->getInputLine('backend_id','backend_id','',TRUE));
		$this->propCount++;
		foreach($this->MAINCFG->getValidObjectType('backend') as $propname => $prop) {
			if($propname == "backendtype") {
				$ret = array_merge($ret,$this->ADDBACKENDFORM->getSelectLine($propname,$propname,array_merge(Array(''=>''),$this->CORE->getAvailableBackends()),'',$prop['must'],"getBackendOptions(this.value,'','".$this->ADDBACKENDFORM->getId()."');"));
				$this->propCount++;
			}
		}
		$ret[] = "<script language=\"javascript\">";
		$ret[] = "\tvar backendOptions = Array();";
		$a = $this->MAINCFG->getValidObjectType('backend');
		foreach($a['options'] AS $backendtype => $arr) {
			$ret[] = "\tbackendOptions['".$backendtype."'] = Array();";
			foreach($arr AS $key => $opt) {
				$ret[] = "\tbackendOptions['".$backendtype."']['".$key."'] = Array();";
				foreach($opt AS $var => $val) {
					$ret[] = "\tbackendOptions['".$backendtype."']['".$key."']['".$var."'] = '".$val."'";
				}
			}
		}
		$ret[] = "</script>";
		return $ret;
	}
	
	/**
	 * Gets default fields of the form
	 *
	 * @return	Array	HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
	function getDefaultFields() {
		$ret = Array();
		
		$ret = array_merge($ret,$this->DEFBACKENDFORM->getSelectLine($this->LANG->getText('defaultBackend'),'defaultbackend',$this->CORE->getDefinedBackends(),$this->MAINCFG->getValue('defaults','backend',TRUE),TRUE));
		$this->propCount++;
		
		return $ret;
	}
	
	/**
	 * Gets all needed messages
	 *
	 * @return	Array JS
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getJsLang() {
		$ret = Array();
		$ret[] = 'var lang = Array();';
		$ret[] = 'lang[\'mustValueNotSet\'] = \''.$this->LANG->getText('mustValueNotSet').'\';';
		
		return $ret;	
	}
}
?>
