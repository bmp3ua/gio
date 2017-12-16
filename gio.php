<?php
/*
Plugin Name: WP True Google Image Optimizer
Author: CA
Version: 1.0.0
*/


class GoogleImageOptimizer {

    private $stat;
    private $current_img;

    function __construct() {
		$upload_dir = wp_upload_dir();
		define( 'GIO_UPLOAD_PATH', $upload_dir['path'] );
        define( 'GIO_UPLOAD_BASE', $upload_dir['basedir'] );
        define( 'GIO_PLUGINS_PATH', WP_PLUGIN_DIR );
        define( 'GIO_THEMES_PATH', get_theme_root() );
		define( 'GIO_TMP', $upload_dir['basedir'] . '/gio_tmp' );
		if(strstr($upload_dir['url'],'http'))
		{
			define('GIO_UPLOAD_URL', $upload_dir['url']);
		}
		else
		{
			define('GIO_UPLOAD_URL', get_site_url() . $upload_dir['url']);
		}
		if(strstr($upload_dir['url'],'http'))
		{
			define('GIO_UPLOAD_BASEURL', $upload_dir['baseurl']);
		}
		else
		{
			define('GIO_UPLOAD_BASEURL', get_site_url() . $upload_dir['baseurl']);
		}

		define('GIO_GOOGLE_OPTIMIZE_URL', 'https://www.googleapis.com/pagespeedonline/v3beta1/optimizeContents?key=AIzaSyArDg0z7O7aPYjEx33gDwdML4QaJnBXiuk&url=');
		define('GIO_GOOGLE_STRATEGY', '&strategy=desktop');
		
		if ( !isset( $_POST['dir'] ) ) $this->dir = GIO_UPLOAD_PATH;
		else $this->dir = $_POST['dir'];

		if ( !is_dir( GIO_TMP ) ) {
			mkdir( GIO_TMP );
		}

		$this->zipResource = fopen( GIO_TMP . '/tmpfile.zip', "w" );

		$this->domain = get_option('siteurl');
		$this->domain = parse_url($domain);
		$this->domain = str_replace('www.','',$domain['host']);

		$this->current_img = $this->stat = array();

	}
	
	
	function init() {

	    add_action('admin_enqueue_scripts', array( $this, 'gio_load_scripts' ));
		add_action( 'admin_init', array( $this, 'gio_init' ) );
		add_action( 'admin_menu', array( $this, 'gio_add_menu_item' ) );
		
	}	
	

	function gio_load_scripts( $hook ) {

		if( 'media_page_true-image-optimizer' == $hook ) {

			wp_register_style( 'gio_css', plugins_url('css/gio_css.css', __FILE__) );
			wp_enqueue_style( 'gio_css' );

			wp_register_script( 'gio_js', plugins_url('js/gio_js.js', __FILE__), array( 'jquery' ) );
			wp_enqueue_script( 'gio_js' );

			add_thickbox();

		}

	}



	function gio_init() {

        add_action( 'wp_ajax_gio_start_optimize', array( $this, 'start_optimize' ) );
		add_action( 'wp_ajax_gio_start_folder_optimize', array( $this, 'start_folder_optimize' ) );
		add_action( 'wp_ajax_gio_optimize_single_img', array( $this, 'optimize_single_img' ) );
		add_action( 'wp_ajax_gio_cancel_optimize', array( $this, 'gio_cancel_optimize' ) );
		add_action( 'admin_init', array( $this, 'gio_init' ) );

	}

	

	function gio_add_menu_item() {
		add_submenu_page(
			'upload.php',
			'True Image Optimizer',
			'True Image Optimizer',
			'manage_options',
			'true-image-optimizer',
			array( $this, 'gio_optimizer_admin_page' ) );
	}

	

	function gio_optimizer_admin_page() {

        $dirs = array( 'upload files directories' => GIO_UPLOAD_BASE, 'themes' => GIO_THEMES_PATH, 'plugins' => GIO_PLUGINS_PATH );
        $dest_dirs = '';

        foreach( $dirs as $name => $dir ) {
            $dest_dirs .= '<ul class="target-box"><li class="target-dir parent-dir" data-value="' . $dir . '"><span class="name">' . $name . '</span>';
            $sub_dirs = '';
            foreach ( glob( $dir . '/*', GLOB_ONLYDIR ) as $subdir ) {
                $sub_dirs .= '<li data-value="' . $subdir . '" class="target-dir child-dir"><span class="name">' . basename( $subdir ) . '</span></li>';
            }
            if ( $sub_dirs != '' ) {
                $dest_dirs .= $sub_dirs;
            }
            $dest_dirs .= '</ul>';
        }

		$out =

			'<div class="admin-gio-box">
				 <div class="controls-box">
				     <div class="controls">
				         <div class="destination">
				             <div class="destination-dirs">' .
                                 $dest_dirs .
                             '</div>
					         <div id="gio-dest" class="gio-dest control" value="' . GIO_UPLOAD_PATH . '">' . GIO_UPLOAD_PATH . '</div>
					     </div>				     
				         <div class="info">
				             <div class="left-side">
				                 <div class="info-input">
				                     <label class="info-label"><span>current file</span><div class="current-file">0</div></label>
				                 </div>
				                 <div class="info-input">
				                     <label class="info-label"><span>total</span><div class="digit total">0</div></label>
				                 </div>
				                 <div class="info-input">
				                     <label class="info-label"><span>progress</span><div class="digit progress">0</div></label>
				                 </div> 
				                 <div class="info-input">   
				                     <label class="info-label"><span>sucessed images</span><div class="digit success">0</div></label>
				                 </div> 
 				                 <div class="info-input">   
				                     <label class="info-label"><span>no needed to perform images</span><div class="digit no-need">0</div></label>
				                 </div>  
 				                 <div class="info-input">  
				                     <label class="info-label"><span>rejected by google API</span><div class="digit rejected">0</div></label>
				                 </div>  
				             </div>    
				             <div class="right-side time">
							     <div class="saving">
								     <div class="title">Saved Kb</div>
								     <span class="saved-bytes">0</span>/<span class="general-bytes">0</span>
							     </div>
								 <div class="general-percentage-box">
								     <div class="title">General Percentage</div>
									 <div class="general-percentage">0%</div>
								 </div>
								 <div class="rejected-imgs-box">
								     <div class="title">rejected images</div>
									 <div class="rejected-imgs"></div>
                                 </div>
							 </div>
				             <div class="gio-results"></div>
				         </div>
					 </div>
					 <button class="gio-start-optimize btn">' . __( 'start' , 'gio' ) . '</button>
					 <button class="gio-cancel-optimize btn">' . __( 'cancel' , 'gio' ) . '</button>
				 </div>
			</div>';

		echo $out;

	}


	function start_optimize() {

        $this->stat['q'] = 0;
	
        if ( preg_match( '/\.jpg|\.png/', $this->dir ) ) {
            echo json_encode( array( 'result' => 1, 'data' => array( $this->dir ), 'content' => __( 'selected folder has subfolder, processing ...' , 'gio' ) ) );
        }
        else {
            $fs = $dirs = $files = array();
            $path = realpath($this->dir);

            $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($objects as $name => $object) {
                if (is_dir($name)) {
                    $dirs[] = $name;
                }
            }

            array_unshift($dirs, $path);
            foreach ($dirs as $dir) {
                $files = glob( $dir . "/*.{jpg,png}", GLOB_BRACE );
                if (count($files) > 0) {
                    $fs[] = $dir;
                }
				$this->stat['q'] = $this->stat['q'] + count($files);
            }

            if (count($fs) > 0) {
                echo json_encode( array('result' => 1, 'data' => $fs, 'content' => __('selected folder has subfolder, processing ...', 'gio'), 'task_files' => $this->stat['q'] ) );
            } else {
                echo json_encode( array('result' => 0, 'data' => 0, 'content' => __('there are no acceseble files or subfolders in selected folder', 'gio'), 'task_files' => 0 ) );
            }
        }

        wp_die();

    }

	
	function start_folder_optimize() {

	    if ( !preg_match( '/\.jpg|\.png/', $this->dir ) )
		    $files = glob( $this->dir . "/*.{jpg,png}", GLOB_BRACE );
		else $files = array ( $this->dir );
		if ( count ( $files ) > 0 ) 
		    echo json_encode( array( 'result' => 1, 'data' => $files, 'content' => __( 'starting optimazing directory files ' . $this->dir , 'gio' ) ) );
		else 
			echo json_encode( array( 'result' => 0, 'content' => __( 'empty directory', 'gio' ) ) );

		wp_die();

	}
	
	function optimize_single_img ( $img = null ) {
		
		/*if ( is_int( $img ) ) {
			
		}
		else if ( is_string( $img ) ) {
			$img = $img;
		}
		else if ( isset( $_POST['action'] ) && isset( $_POST['img'] ) ) {
			$img = $_POST['img'];
		}*/
		$img = $_POST['img'];

		
		$this->gio_optimize_file( $img );
		wp_die();
		
		
	}


	function gio_optimize_file( $file ) {
		
		$size = getimagesize($file);
		$w = (int)$size[0]; $h = (int)$size[1];
		
		if ( filesize( $file ) < 8*1024*1024 && $w <= 1440 ) {

			$url = $this->abs_path_to_url( $file ); 
			$url = 'https://www.googleapis.com/pagespeedonline/v3beta1/optimizeContents?key=AIzaSyArDg0z7O7aPYjEx33gDwdML4QaJnBXiuk&url=' . $url . '&strategy=desktop';
			$result = $this->gio_google_request( $url, $file );
		
		}
		else {
			$content = file_get_contents( GIO_UPLOAD_PATH . '/gio_log.txt' );
			$content .= $file . ' size=' . filesize( $file ) . ', width = ' . $w . ', height=' . $h . PHP_EOL;
			file_put_contents( GIO_UPLOAD_BASE . '/gio_log.txt', $content );
			$this->current_img = array( 'file' => $file, 'start_size' => 0, 'finish_size' => 0, 'result' => false );
			$result = array ( 'result' => 2, 'content' => __( 'file', 'gio' ) . ' ' . $this->get_front_filename( $file ) . __( 'has size ', 'gio' ) . filesize( $file ) . ', and width ' . $w . __( ' it must be under 8Mb size and 1024px width, to check by Google API', 'gio' ), 'current_img' => $this->current_img );
			
		}
		
		echo json_encode( $result );

	}


	function gio_cancel_optimize() {

	    //file_put_contents( GIO_UPLOAD_BASE . '/gio_log.txt', '' );
		//array_map('unlink', glob( GIO_TMP . '/*' ));
		//rmdir( GIO_TMP );
        $this->recursiveDelete( GIO_TMP );
		wp_die();

	}

	function abs_path_to_url( $path = '' ) {
		$url = str_replace(
			wp_normalize_path( untrailingslashit( ABSPATH ) ),
			site_url(),
			wp_normalize_path( $path )
		);
		return esc_url_raw( $url );
	}


	function gio_google_request( $url, $file ) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_REFERER, $this->domain);
		curl_setopt($ch, CURLOPT_FILE, $this->zipResource);
		$r = curl_exec($ch);

		//return array( 'result' => 0, 'content' => __( 'http request error, try later', 'gio' ) );
        //return array ( 'result' => 1, 'content' => __( 'no need to compress', 'gio' ) . ' ' . $file ) ;
		if(curl_errno($ch)){
			return array( 'result' => 0, 'content' => __( 'http request error, try later', 'gio' ) );
		}
		curl_close($ch);
		$result = $this->unzip_and_get_filename( $file );
		if ( $result ) 
			return array( 'result' => 1, 'content' => __( 'file', 'gio' ) . ' ' . $this->get_front_filename( $file ) . ' ' . __( 'compressed succefully - ' . $this->current_img['save'] . ' bytes saved (' . round ( $this->current_img['save']/$this->current_img['start']*100, 2 ) . '%). ' , 'gio' ), 'current_img' => $this->current_img );
		else {
			$this->set_stat( array( 'file' => $file, 'start_size' => $start_size, 'finish_size' => filesize( $file ), 'result' => true ) );
			return array ( 'result' => 3, 'content' => __( 'no need to compress', 'gio' ) . ' ' . $this->get_front_filename( $file ), 'current_img' => $this->current_img ) ;
		}

	}


	function unzip_and_get_filename( $file )
	{

	    $start_size = filesize( $file );
		$zip = new ZipArchive;

		$zip->open( GIO_TMP . '/tmpfile.zip' );
		if( $zip->getNameIndex(0) !== 'MANIFEST' )
		{
			$zip->extractTo( GIO_TMP . '/' );
			$tmp_path = $zip->getNameIndex(0);
			$finish_size = filesize( GIO_TMP . '/' . $tmp_path );
			
			$ratio = $finish_size/$start_size;
			if ( $ratio < 0.06 ) {
				//$sub_url = 'https://www.googleapis.com/pagespeedonline/v3beta1/optimizeContents?key=AIzaSyArDg0z7O7aPYjEx33gDwdML4QaJnBXiuk&url=' . site_url() . '/rocket' . '&strategy=desktop';
				//$sub_result = $this->gio_google_request( $sub_url, $file )
				$zip->close();
				$this->set_stat( array( 'file' => $file, 'start_size' => $start_size, 'finish_size' => filesize( $file ), 'result' => false ) );
				return false;				
			}
			
			$result = rename( GIO_TMP . '/' . $tmp_path, $file ); 
			array_map('unlink', glob( GIO_TMP . '/*.*' ));

			$this->set_stat( array( 'file' => $file, 'start_size' => $start_size, 'finish_size' => $finish_size, 'result' => true ) );

			return $result;
		}
		else
		{
			$zip->close();
            $this->set_stat( array( 'file' => $file, 'start_size' => $start_size, 'finish_size' => filesize( $file ), 'result' => false ) );
			return false;
		}
	}

	function set_stat ( $args = array( 'file' => null, 'start_size' => 0, 'finish_size' => 0, 'result' => false ) ) {

	    $this->current_img = array();
	
        if ( args['result'] ) {
            $this->current_img['result'] = 1;
			$this->current_img['start'] = $args['start_size'];
			$this->current_img['finish'] = $args['finish_size'];
			$this->current_img['save'] = $args['start_size'] - $args['finish_size'];
			$this->current_img['type'] = 'success';
        }
        else {
            $this->current_img['result'] = 0;
			$this->current_img['start'] = $args['start_size'];
			$this->current_img['finish'] = $args['finish_size'];
			$this->current_img['save'] = 0; 
            $this->current_img['type'] = 'passed';			 
        }

    }

    function recursiveDelete($path, $deleteParent = true){

        if(!empty($path) && is_dir($path) ){
            $dir  = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS); //upper dirs are not included,otherwise DISASTER HAPPENS :)
            $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) {
                if ( is_file($f->getPathName())) {
                    unlink($f->getPathName());
                    //$f->getPathName();
                }
                else {
                    $empty_dirs[] = $f;
                }
            }
            if (!empty($empty_dirs)) {
                foreach ($empty_dirs as $eachDir) {
                    rmdir($eachDir);
                }
            }
            rmdir($path);
        }

    }
	
	function get_front_filename( $file ) {
		$r = preg_match( '/\/wp-content.+/', $file, $name );
		if ( isset( $name[0] ) ) return $name[0];
		else return '';
	}


}


$inst = new GoogleImageOptimizer();
$inst->init();
