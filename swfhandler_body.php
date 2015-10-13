<?php

// The ImageHandler parts are terribly documented. @bawolff tells me he'll fix it someday

class swfhandler extends ImageHandler
{
	function isEnabled() { return true; }
	
	function getThumbType( $ext, $mime, $params = null ) {
		return array( 'png', 'image/png' );
	}
	
	function getMetadataType( $image ) {
		return 'swf';
	}
	
	function getImageSize( $image, $path ) 
	{
		// swfdump -XY
		// the string looks like -X 100 -Y 100 -r 24.00 -f 1
		// so [1] is X and [3] is Y
		$shellret = wfShellExec( "swfdump -XY ". wfEscapeShellArg( $path ) . " 2>&1", $retval );

		wfDebug( __METHOD__.": shellret: {$shellret}\n" );
		$expandeddims = (explode ( ' ', $shellret ));
		
		$width = $expandeddims[1] ? $expandeddims[1] : null;
		$height = $expandeddims[3] ? $expandeddims[3] : null;
		
		return array ($width, $height );	
	}
	
	// TODO: Should this func be refactored for a TransformableImageHandler?
	function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ) 
	{
		if ($params['width'] == 0) {
		        $params['width'] = $image->getWidth();
		}
		if (!array_key_exists('height',$params) || $params['height'] == 0) {
		        $params['height'] = $image->getHeight();
		}
			
		wfDebug( __METHOD__.": params['width']: {$params['width']} params['height']: {$params['height']}\n" );
		
		if ( !$this->normaliseParams( $image, $params ) ) {
			return new TransformParameterError( $params );
		}
		// if mediawiki tells us to take a vacation, do it, otherwise EXCEPTION
		if ( $flags & self::TRANSFORM_LATER )
		{
			return new ThumbnailImage( $image, $dstUrl, false, $params );
		}

		wfDebug( __METHOD__.": params['width']: {$params['width']} params['height']: {$params['height']}\n" );
		
		$clientWidth = $params['width'];
		$clientHeight = $params['height'];
		
		$srcWidth = $image->getWidth();
		$srcHeight = $image->getHeight();

		$srcPath = $image->getLocalRefPath();

		$retval1 = 0;
		$retval2 = 0;
		$err1 = "not executed";
		$err1 = "not executed";
				
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
		$cmd2 = "mogrify -quality 2 -resize {$outWidth}x{$outHeight} ". wfEscapeShellArg( $dstPath );

		wfProfileIn( 'convert' );
		$err1 = wfShellExecWithStderr($cmd1, $retval1);
		$err2 = "not executed"; // otherwise it isn't defined
		if ( $retval1 == 0 )
		{
			$err2 = wfShellExecWithStderr($cmd2, $retval2);
		}
		wfProfileOut( 'convert' );

		// summarize the results by adding them together
		$retval = $retval1 + $retval2;

		$removed = $this->removeBadFile( $dstPath, $retval );

		if ( $retval != 0 || $removed ) {
			wfDebugLog( 'thumbnail',
				sprintf( 'thumbnail failed on %s: errors ', wfHostname() ) );
			wfDebugLog( 'thumbnail',
				sprintf( 'thumbnail failed on step 1: errors %d "%s" from "%s"',
					$retval1, trim($err1), $cmd1 ) );
			wfDebugLog( 'thumbnail',
				sprintf( 'thumbnail failed on step 2: errors %d "%s" from "%s"',
					$retval2, trim($err2), $cmd2 ) );
			return new MediaTransformError( 'thumbnail_error', $clientWidth, $clientHeight,
				sprintf("(error 1: %s; error 2: %s)", $err1, $err2) );
		} 
		else 
		{
			return new ThumbnailImage( $image, $dstUrl, $clientWidth, $clientHeight, $dstPath );
		}
	}
	
	// I lied: it's not length, it's the # of frames. (we could do an algorithm to determine
	// the length via the frames and framerate, but lazy) Also, do trim because it mangles
	// printing otherwise
	function getLength( $image ) 
	{	
		$shellret = wfShellExec( "swfdump -f ". wfEscapeShellArg( $image->getLocalRefPath() ) . " 2>&1", $retval );
	
		wfDebug( __METHOD__.": shellret: {$shellret}\n" );
	
		// parse output
		$result = explode(" ", $shellret);
				
		$duration = $result [1] ? $result [1] : null;
		
		wfDebug( __METHOD__.": frames: {$duration}\n" );
		
		return trim($duration);
	}

	function getFps( $image ) 
	{	
		$shellret = wfShellExec( "swfdump -r ". wfEscapeShellArg( $image->getLocalRefPath() ) . " 2>&1", $retval );
	
		wfDebug( __METHOD__.": shellret: {$shellret}\n" );
	
		// parse output
		$result = explode(" ", $shellret);
				
		$fps = $result [1] ? $result [1] : null;
		
		wfDebug( __METHOD__.": fps: {$fps}\n" );
		
		return trim($fps);
	}

	function getLongDesc( $image ) {
		global $wgLang;
		return wfMsgExt('swf-long-video', 'parseinline',
			$wgLang->formatNum( $image->getWidth() ),
			$wgLang->formatNum( $image->getHeight() ),
			$wgLang->formatSize( $image->getSize() ),
			$wgLang->formatNum( $image->getLength( $image ) ),
			$wgLang->formatNum( $this->getFps( $image ) ),
			$image->getMimeType() );
	}
}
