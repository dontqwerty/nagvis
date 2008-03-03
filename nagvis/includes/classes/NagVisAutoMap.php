<?php
/**
 * Class for NagVis Automap generating
 */
class NagVisAutoMap extends GlobalMap {
	var $MAINCFG;
	var $MAPCFG;
	var $MAPOBJ;
	var $LANG;
	var $BACKEND;
	
	var $preview;
	
	var $backend_id;
	var $root;
	var $maxLayers;
	var $width;
	var $height;
	var $renderMode;
	var $ignoreHosts;
	var $filterGroup;
	
	var $rootObject;
	var $arrMapObjects;
	var $arrHostnames;
	
	var $mapCode;
	
	/**
	 * Automap constructor
	 *
	 * @param		MAINCFG		Object of NagVisMainCfg
	 * @param		LANG			Object of GlobalLanguage
	 * @param		BACKEND		Object of GlobalBackendMgmt
	 * @return	String 		Graphviz configuration
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function NagVisAutoMap(&$MAINCFG, &$LANG, &$BACKEND, &$prop) {
		$this->MAINCFG = &$MAINCFG;
		$this->LANG = &$LANG;
		$this->BACKEND = &$BACKEND;
		
		$this->arrHostnames = Array();
		$this->arrMapObjects = Array();
		$this->mapCode = '';
		
		// Create map configuration
		$this->MAPCFG = new NagVisMapCfg($this->MAINCFG, '__automap');
		$this->MAPCFG->readMapConfig();

		// Set the preview option
		if(isset($prop['preview']) && $prop['preview'] != '') {
			$this->preview = $prop['preview'];
		} else {
			$this->preview = 0;
		}
		
		// Do the preflight checks
		$this->checkPreflight();
		
		if(isset($prop['backend']) && $prop['backend'] != '') {
			$this->backend_id = $prop['backend'];
		} else {
			$this->backend_id = $this->MAINCFG->getValue('defaults', 'backend');
		}
		
		/**
		 * This is the name of the root host, user can set this via URL. If no
		 * hostname is given NagVis tries to take configured host from main
		 * configuration or read the host which has no parent from backend
		 */
		if(isset($prop['root']) && $prop['root'] != '') {
			$this->root = $prop['root'];
		}else {
			$this->root = $this->getRootHostName();
		}
		
		/**
		 * This sets how much layers should be displayed. Default value is -1, 
		 * this means no limitation.
		 */
		if(isset($prop['maxLayers']) && $prop['maxLayers'] != '') {
			$this->maxLayers = $prop['maxLayers'];
		} else {
			$this->maxLayers = -1;
		}
		
		/**
		 * The renderMode can be set via URL, if no is given NagVis takes the "tree"
		 * mode
		 */
		if(isset($prop['renderMode']) && $prop['renderMode'] != '') {
			$this->renderMode = $prop['renderMode'];
		} else {
			$this->renderMode = 'undirected';
		}
		
		if(isset($prop['width']) && $prop['width'] != '') {
			$this->width = $prop['width'];
		} else {
			$this->width = 1024;
		}
		
		if(isset($prop['height']) && $prop['height'] != '') {
			$this->height = $prop['height'];
		} else {
			$this->height = 786;
		}
		
		if(isset($prop['ignoreHosts']) && $prop['ignoreHosts'] != '') {
			$this->ignoreHosts = explode(',', $prop['ignoreHosts']);
		} else {
			$this->ignoreHosts = Array();
		}
		
		if(isset($prop['filterGroup']) && $prop['filterGroup'] != '') {
			$this->filterGroup = $prop['filterGroup'];
		} else {
			$this->filterGroup = '';
		}
		
		// Get "root" host object
		$this->fetchHostObjectByName($this->root);
		
		// Get all object informations from backend
		$this->getObjectTree();
		
		if($this->filterGroup != '') {
			$this->filterGroupObject = new NagiosHostgroup($this->MAINCFG, $this->BACKEND, $this->LANG, $this->backend_id, $this->filterGroup);
			$this->filterGroupObject->fetchMemberHostObjects();
			
			$this->filterObjectTreeByGroup();
		}
		
		parent::GlobalMap($this->MAINCFG, $this->MAPCFG);
		
		// Create MAPOBJ object, form the object tree to map objects and get the
		// state of the objects
		$this->MAPOBJ = new NagVisMapObj($this->MAINCFG, $this->BACKEND, $this->LANG, $this->MAPCFG);
		$this->MAPOBJ->objectTreeToMapObjects($this->rootObject);
		$this->MAPOBJ->fetchState();
	}
	
	/**
	 * Parses the graphviz config of the autmap
	 *
	 * @return	String 		Graphviz configuration
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function parseGraphvizConfig() {
		
		/**
		 * Graph definition
		 */
		$str  = 'graph automap { ';
		//, ranksep="0.1", nodesep="0.4", ratio=auto, bb="0,0,500,500"
		$str .= 'graph [';
		$str .= 'dpi="72", ';
		//ratio: expand, auto, fill, compress
		$str .= 'ratio="fill", ';
		$str .= 'root="'.$this->rootObject->getType().'_'.$this->rootObject->getName().'", ';
		
		/* Directed (dot) only */
		if($this->renderMode == 'directed') {
			$str .= 'nodesep="0", ';
			//rankdir: LR,
			//$str .= 'rankdir="LR", ';
			//$str .= 'compound=true, ';
			//$str .= 'concentrate=true, ';
			//$str .= 'constraint=false, ';
		}
		
		/* Directed (dot) and radial (twopi) only */
		if($this->renderMode == 'directed' || $this->renderMode == 'radial') {
			$str .= 'ranksep="0.8", ';
		}
		
		/* Only for circular (circo) mode */
		if($this->renderMode == 'circular') {
			//$str .= 'mindist="1.0", ';
		}
		
		/* All but directed (dot) */
		if($this->renderMode != 'directed') {
			//overlap: true,false,scale,scalexy,ortho,orthoxy,orthoyx,compress,ipsep,vpsc
			//$str .= 'overlap="ipsep", ';
		}
		
		$str .= 'size="'.$this->pxToInch($this->width).','.$this->pxToInch($this->height).'"]; '."\n";
		
		/**
		 * Default settings for automap nodes
		 */
		$str .= 'node [';
		// default margin is 0.11,0.055
		$str .= 'margin="0.0,0.0", ';
		$str .= 'ratio="auto", ';
		$str .= 'shape="none", ';
		$str .= 'fontcolor=black, fontsize=10';
		$str .= '];'."\n ";
		
		// Create nodes for all hosts
		$str .= $this->rootObject->parseGraphviz();
		
		$str .= '} ';
		
		return $str;
	}
	
	/**
	 * Renders the map image, saves it to var/ directory and creates the map and
	 * ares for the links
	 *
	 * @return	Array		HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function renderMap() {
		/**
		 * possible render modes are set by selecting the correct binary:
		 *  dot - filter for drawing directed graphs
		 *  neato - filter for drawing undirected graphs
		 *  twopi - filter for radial layouts of graphs
		 *  circo - filter for circular layout of graphs
		 *  fdp - filter for drawing undirected graphs
		 */
		switch($this->renderMode) {
			case 'directed':
				$binary = 'dot';
			break;
			case 'undirected':
				$binary = 'neato';
			break;
			case 'radial':
				$binary = 'twopi';
			break;
			case 'circular':
				$binary = 'circo';
			break;
			case 'undirected2':
				$binary = 'fdp';
			break;
			default:
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:automap'));
				$FRONTEND->messageToUser('ERROR','unknownRenderMode','MODE~'.$this->renderMode);
			break;
		}
		
		/**
		 * The config can not be forwarded to graphviz binary by echo, this would
		 * cause in too long commands with big maps. SO write thoe config to a file
		 * and let it be read by graphviz binary.
		 */
		$fh = fopen($this->MAINCFG->getValue('paths', 'var').'automap.dot','w');
		fwrite($fh, $this->parseGraphvizConfig());
		fclose($fh);
		
		// Parse map
		exec($this->MAINCFG->getValue('automap','graphvizpath').$binary.' -Tpng -o \''.$this->MAINCFG->getValue('paths', 'var').'automap.png\' -Tcmapx '.$this->MAINCFG->getValue('paths', 'var').'automap.dot', $arrMapCode);
		
		$this->mapCode = implode("\n", $arrMapCode);
	}
	
	/**
	 * Replaces some unwanted things from graphviz html code
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function fixMapCode() {
		/**
		 * The hover menu can't be rendered in graphviz config. The informations
		 * which are needed here are rendered like this title="<host_name>".
		 *
		 * The best idea I have for this: Extract the hostname and replace
		 * title="<hostname>" with the hover menu code.
		 */
		
		foreach($this->MAPOBJ->getMapObjects() AS $OBJ) {
			$this->mapCode = str_replace('title="'.$OBJ->getName().'"', $OBJ->getHoverMenu(), $this->mapCode);
		}
	}
	
	/**
	 * Parses the Automap HTML code
	 *
	 * @return	Array		HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function parseMap() {
		$ret = Array();
		
		// Render the map image and save it, also generate link coords etc
		$this->renderMap();
		
		// Fix the map code
		$this->fixMapCode();
		
		// Create HTML code for background image
		$ret = $this->getBackground();
		
		// Parse the map with its areas
		$ret[] = $this->mapCode;
		
		// Dynamicaly set favicon
		$ret[] = $this->getFavicon();
		
		// Change title (add map alias and map state)
		$ret[] = '<script type="text/javascript" language="JavaScript">document.title=\''.$this->MAPCFG->getValue('global', 0, 'alias').' ('.$this->MAPOBJ->getSummaryState().') :: \'+document.title;</script>';
		
		return $ret;
	}
	
	/**
	 * Gets the background of the map
	 *
	 * @return	Array	HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getBackground() {
		// Append random number to prevent caching
		$src = $this->MAINCFG->getValue('paths', 'htmlvar').'automap.png?'.mt_rand(0,10000);
		
		return $this->getBackgroundHtml($src,'','usemap="#automap"');
	}
	
	# END Public Methods
	# #####################################################
	
	/**
	 * Do the preflight checks to ensure the automap can be drawn
	 */
	function checkPreflight() {
		// If this is a preview for the index page do not print errors
		if($this->preview) {
			$printErr = 0;
		} else {
			$printErr = 1;
		}
		
		// The GD-Libs are used by graphviz
		$this->checkGd($printErr);
		
		$this->checkVarFolderWriteable($printErr);
		
		// Check all possibly used binaries of graphviz
		$this->checkGraphviz('dot', $printErr);
		$this->checkGraphviz('neato', $printErr);
		$this->checkGraphviz('twopi', $printErr);
		$this->checkGraphviz('circo', $printErr);
		$this->checkGraphviz('fdp', $printErr);
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
		if($this->checkVarFolderExists($printErr) && is_writable(substr($this->MAINCFG->getValue('paths', 'var'),0,-1))) {
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
	 * Checks if the Graphviz binaries can be found on the system
	 *
	 * @param		String	Filename of the binary
	 * @param		Bool		Print error message?
	 * @return	String	HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function checkGraphviz($binary, $printErr) {
		/**
		 * Check if the carphviz binaries can be found in the PATH or in the 
		 * configured path
		 */
		// Check if dot can be found in path (If it is ther $returnCode is 0, if not it is 1)
		exec('which '.$binary, $arrReturn, $returnCode1);
		
		if(!$returnCode1) {
			$this->MAINCFG->setValue('automap','graphvizpath',str_replace($binary,'',$arrReturn[0]));
		}
		
		exec('which '.$this->MAINCFG->getValue('automap','graphvizpath').$binary, $arrReturn, $returnCode2);
		
		if(!$returnCode2) {
			$this->MAINCFG->setValue('automap','graphvizpath',str_replace($binary,'',$arrReturn[0]));
		}
		
		if($returnCode1 & $returnCode2) {
			if($printErr) {
				$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:automap'));
				$FRONTEND->messageToUser('ERROR','graphvizBinaryNotFound','NAME~'.$binary.',PATHS~'.$_SERVER['PATH'].':'.$this->MAINCFG->getvalue('automap','graphvizpath'));
			}
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	/**
	 * Gets the favicon of the page representation the state of the map
	 *
	 * @return	String	HTML Code
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getFavicon() {
		if(file_exists('./images/internal/favicon_'.strtolower($this->MAPOBJ->getSummaryState()).'.png')) {
			$favicon = './images/internal/favicon_'.strtolower($this->MAPOBJ->getSummaryState()).'.png';
		} else {
			$favicon = './images/internal/favicon.png';
		}
		return '<script type="text/javascript" language="JavaScript">favicon.change(\''.$favicon.'\'); </script>';
	}
	
	/**
	 * This methods converts pixels to inches
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function pxToInch($px) {
		return round($px/72, 4);
	}
	
	/**
	 * Get all child objects
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getObjectTree() {
		$this->rootObject->fetchChilds($this->maxLayers, $this->getObjectConfiguration(), $this->ignoreHosts, $this->arrHostnames, $this->arrMapObjects);
	}
	
	/**
	 * Filter the object tree by the given filter group
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function filterObjectTreeByGroup() {
		$hostgroupMembers = Array();
		foreach($this->filterGroupObject->getMembers() AS $OBJ1) {
			$hostgroupMembers[] = $OBJ1->getName();
		}
		
		$this->rootObject->filterChilds($hostgroupMembers);
	}
	
	/**
	 * Gets the configuration of the objects by the global configuration
	 *
	 * @return	Array		Object configuration
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function &getObjectConfiguration() {
		$objConf = Array();
		
		// Get object configuration from __automap configuration
		foreach($this->MAPCFG->validConfig['host'] AS $key => &$values) {
			if($key != 'type' && $key != 'backend_id' && $key != 'host_name') {
				$objConf[$key] = $this->MAPCFG->getValue('global', 0, $key);
			}
		}
		
		return $objConf;
	}
	
	/**
	 * Get root host object by NagVis configuration or by backend.
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function getRootHostName() {
		/**
		 * NagVis tries to take configured host from main
		 * configuration or read the host which has no parent from backend
		 */
		$defaultRoot = $this->MAINCFG->getValue('automap','defaultroot', TRUE);
		if(isset($defaultRoot) && $defaultRoot != '') {
			return $defaultRoot;
		} else {
			if($this->BACKEND->checkBackendInitialized($this->backend_id, TRUE)) {
				$hostsWithoutParent = $this->BACKEND->BACKENDS[$this->backend_id]->getHostNamesWithNoParent();
				if(count($hostsWithoutParent) == 1) {
					return $hostsWithoutParent[0];
				} else {
					// Could not get root host for the automap
					$FRONTEND = new GlobalPage($this->MAINCFG,Array('languageRoot'=>'nagvis:automap'));
					$FRONTEND->messageToUser('ERROR','couldNotGetRootHostname');
				}
			}
		}
	}
	
	/**
	 * Creates a host object by the host name
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function fetchHostObjectByName($hostName) {
		$hostObject = new NagVisHost($this->MAINCFG, $this->BACKEND, $this->LANG, $this->backend_id, $hostName);
		$hostObject->fetchMembers();
		$hostObject->setConfiguration($this->getObjectConfiguration());
		$this->rootObject = $hostObject;
	}
}
?>
