<?php

// MediaHandler::getParamMap, MediaHandler::validateParam, MediaHandler::makeParamString
// MediaHandler::parseParamString, MediaHandler::normaliseParams, MediaHandler::getImageSize

class swfhandler extends ImageHandler
{
	function isEnabled() { return true; }
	
	function canRender( $file ) { return true; }
	function mustRender( $file ) { return true; }
	
	function getThumbType( $ext, $mime ) {
		return array( 'png', 'image/png' );
	}
	
	function getMetadataType( $image ) {
		return 'swf';
	}
	
	function normaliseParams( $image, &$params ) {
	        $mimeType = $image->getMimeType();
	
	        if ( !isset( $params['width'] ) ) {
	                return false;
	        }
	
	    	$gis = $image->getImageSize( $image, $image->getPath() );
		    		
	        $srcWidth = $gis[0];
	        $srcHeight = $gis[1];
				
			//wfDebug( __METHOD__.": srcWidth: {$srcWidth} srcHeight: {$srcHeight}\n" );
			
	        if ( isset( $params['height'] ) && $params['height'] != -1 ) {
	                if ( $params['width'] * $srcHeight > $params['height'] * $srcWidth ) {
	                        $params['width'] = wfFitBoxWidth( $srcWidth, $srcHeight, $params['height'] );
	                }
	        }
	
	        $params['height'] = File::scaleHeight( $srcWidth, $srcHeight, $params['width'] );
	        // if ( !$this->validateThumbParams( $params['width'], $params['height'], $srcWidth, $srcHeight, $mimeType ) ) {
	        //         return false;
	        // }
	
	
			//wfDebug( __METHOD__.": srcWidth: {$srcWidth} srcHeight: {$srcHeight}\n" );
			
	        return true;
	}
	
	function getPageDimensions( $image, $page ) {
    	$gis = $this->getImageSize( $image, $image->getPath() );
	    return array(
	            'width' => $gis[0],
	            'height' => $gis[1]
	    );
	}
	
	
	function getImageSize( $image, $path ) 
	{
		
//		$varinfo = var_export($image, true);
//		wfDebug( __METHOD__.": image: {$varinfo}\n" );
		
//		$varinfo = var_export($path, true);
//		wfDebug( __METHOD__.": path: {$varinfo}\n" );
		
		// kludge to deal with path being set in different variables coming from api vs normal calls:
		if (isset($image->path))
		{
			// normal call has $image->path set but $path not set
			$mypath=$image->path;
		}
		else
		{
			// api has $path set but $image->path not set
			$mypath=$path;
		}
		
		// swfdump -XY
		// the string looks like -X 100 -Y 100 -r 24.00 -f 1
		// so [1] is X and [3] is Y
		$shellret = wfShellExec( "swfbbox -XY ". wfEscapeShellArg( $mypath ) . " 2>&1", $retval );

		//wfDebug( __METHOD__.": shellret: {$shellret}\n" );
		$expandeddims = (explode ( 'x', $shellret ));
		
		$width = $expandeddims [1] ? $expandeddims [1] : null;
		$height = $expandeddims [3] ? $expandeddims [3] : null;
		
		return array ($width, $height );	
    }
	

	function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ) 
	{
			
		if ($params['width'] == 0) {
			$gis = $image->getImageSize( $image, $image->getPath() );

		        $params['width'] = $gis[0];
		}
		if (!array_key_exists('height',$params) || $params['height'] == 0) {
			$gis = $image->getImageSize( $image, $image->getPath() );

		        $params['height'] = $gis[1];
		}
			
		wfDebug( __METHOD__.": params['width']: {$params['width']} params['height']: {$params['height']}\n" );
		
			
		if ( !$this->normaliseParams( $image, $params ) ) {
			return new TransformParameterError( $params );
		}
		
		wfDebug( __METHOD__.": params['width']: {$params['width']} params['height']: {$params['height']}\n" );
		
		$clientWidth = $params['width'];
		$clientHeight = $params['height'];
		
		// return thumb if already exists (and is valid image of correct size)
		if (file_exists($dstPath) ) {
			// list($width, $height, $type, $attr) = getimagesize($dstPath);
			// if (($width == $clientWidth) && ($height == $clientHeight))
			// {
		    return new ThumbnailImage( $image, $dstUrl, $clientWidth, $clientHeight, $dstPath );
			// }
		}
		
		
    	$gis = $image->getImageSize( $image, $image->getPath() );
	
        $srcWidth = $gis[0];
        $srcHeight = $gis[1];

		$srcPath = $image->getPath();
		$retval = 0;
		
				
		$outWidth=$clientWidth;
		$outHeight=$clientHeight;

		// if ( $outWidth == $srcWidth && $outWidth == $srcHeight ) {
		// 	# normaliseParams (or the user) wants us to return the unscaled image
		// 	wfDebug( __METHOD__.": returning unscaled image\n" );
		// 	return new ThumbnailImage( $image, $image->getURL(), $clientWidth, $clientHeight, $srcPath );
		// }


		wfMkdirParents( dirname( $dstPath ) );

		wfDebug( __METHOD__.": creating {$outWidth}x{$outHeight} thumbnail at $dstPath\n" );

		// we render then scale
		$cmd1 = "swfrender " . wfEscapeShellArg( $srcPath )." -o ". wfEscapeShellArg( $dstPath );

		$cmd2 = "/usr/bin/mogrify -quality 2 -resize {$outWidth}x{$outHeight} ". wfEscapeShellArg( $dstPath );


		//echo ": Running swfrender: $cmd\n";
		wfDebug( __METHOD__.": Running swfrender: $cmd\n" );
		wfProfileIn( 'convert' );
		$err = wfShellExec( $cmd1, $retval );
		if ( $retval == 0 )
		{
			$err = wfShellExec( $cmd2, $retval );
		}
		wfProfileOut( 'convert' );

		$removed = $this->removeBadFile( $dstPath, $retval );

		if ( $retval != 0 || $removed ) {
			wfDebugLog( 'thumbnail',
				sprintf( 'thumbnail failed on %s: error %d "%s" from "%s"',
					wfHostname(), $retval, trim($err), $cmd ) );
			return new MediaTransformError( 'thumbnail_error', $clientWidth, $clientHeight, $err );
		} 
		else 
		{
			return new ThumbnailImage( $image, $dstUrl, $clientWidth, $clientHeight, $dstPath );
		}
	}
	
	// I lied: it's not length, it's the # of frames (we could do an algorithm to determine
	// the length via the frames and framerate, but lazy)
	function getLength( $image ) 
	{	
		$shellret = wfShellExec( "swfdump -f ". wfEscapeShellArg( $image->getPath() ) . " 2>&1", $retval );
	
		//wfDebug( __METHOD__.": shellret: {$shellret}\n" );
	
		// parse output
		$result = explode(" ", $shellret);
				
		$duration = $result [1] ? $result [1] : null;
		
		wfDebug( __METHOD__.": frames: {$duration}\n" );
		
		return $duration;
	}

	function getFps( $image ) 
	{	
		$shellret = wfShellExec( "swfdump -r ". wfEscapeShellArg( $image->getPath() ) . " 2>&1", $retval );
	
		//wfDebug( __METHOD__.": shellret: {$shellret}\n" );
	
		// parse output
		result = explode(" ", $shellret);
				
		$fps = $result [1] ? $result [1] : null;
		
		wfDebug( __METHOD__.": fps: {$fps}\n" );
		
		return $fps;
	}

	function getShortDesc( $image ) {
		global $wgLang;
		
		$gis = $image->getImageSize( $image, $image->getPath() );

	        $srcWidth = $gis[0];
	        $srcHeight = $gis[1];
		
		$nbytes = wfMsgExt( 'nbytes', array( 'parsemag', 'escape' ),
			$wgLang->formatNum( $image->getSize() ) );
		$widthheight = wfMsgHtml( 'widthheight', $wgLang->formatNum( $srcWidth) ,$wgLang->formatNum( $srcHeight) );

		return "$widthheight ($nbytes)";
	}

	function getLongDesc( $image ) {
		global $wgLang;
		$gis = $image->getImageSize( $image, $image->getPath() );

	        $srcWidth = $gis[0];
	        $srcHeight = $gis[1];
		return wfMsgExt('swf-long-video', 'parseinline',
			$wgLang->formatNum( $srcWidth ),
			$wgLang->formatNum( $srcHeight ),
			$wgLang->formatSize( $image->getSize() ),
			$image->getLength( $image ),
			$this->getFps( $image ),
			$image->getMimeType() );
	}

	function getDimensionsString( $image ) {
		global $wgLang;
		
		$gis = $image->getImageSize( $image, $image->getPath() );

	        $srcWidth = $gis[0];
	        $srcHeight = $gis[1];
		
		$width = $wgLang->formatNum( $srcWidth );
		$height = $wgLang->formatNum( $srcHeight );

		return wfMsg( 'widthheight', $width, $height );

	}

}
