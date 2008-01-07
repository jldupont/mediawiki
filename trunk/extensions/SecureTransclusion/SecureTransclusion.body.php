<?php
/**
 * @author Jean-Lou Dupont
 * @package SecureTransclusion
 * @version @@package-version@@
 * @Id $Id$
 */
//<source lang=php>
class SecureTransclusion
{
	const thisType = 'other';
	const thisName = 'SecureTransclusion';
	
	public function mg_strans( &$parser, $page, $errorMessage = null, $timeout = 5 )
	{
		if (!self::checkExecuteRight( $parser->mTitle ))
			return 'SecureTransclusion: '.wfMsg('badaccess');
		
		$title = Title::newFromText( $page );
		if (!is_object( $title ))
			return 'SecureTransclusion: '.wfMsg('badtitle');
		
		if ( $title->isTrans() )
			return $this->getRemotePage( $parser, $title, $errorMessage, $timeout );
		
		return $this->getLocalPage( $title, $errorMessage );
	}
	/**
	 * Retrieves a local page.
	 */
	protected function getLocalPage( &$title, $error_msg )
	{
		$contents = $error_msg;
		$rev = Revision::newFromTitle( $title );
		if( is_object( $rev ) )
		    $contents = $rev->getText();		
		return $contents;		
	}
	/**
	 * Retrieves a page located on a remote server.
	 */
	protected function getRemotePage( &$parser, &$title, &$error_msg, $timeout )
	{
		$uri = $title->getFullUrl();
		$text = $this->fetch( $uri, $timeout );
		
		// if we didn't succeed, turn off parser caching
		// hoping to get lucky next time around.
		if (false === $text)
		{
			$parser->disableCache();
			$text = $error_msg;
		}
			
		return $text;
	}	 
	/**
		1- IF the page is protected for 'edit' THEN allow execution
		2- IF the page's last contributor had the 'strans' right THEN allow execution
		3- ELSE deny execution
	 */
	private static function checkExecuteRight( &$title )
	{
		/*
		global $wgUser;
		if ($wgUser->isAllowed('strans'))
			return true;
		*/
		if ($title->isProtected('edit'))
			return true;
		
		// Last resort; check the last contributor.
		/*
		$rev    = Revision::newFromTitle( $title );
		
		$user = User::newFromId( $rev->mUser );
		$user->load();
		
		if ($user->isAllowed( 'strans' ))
			return true;
		*/
		return false;
	}
	/**
	 * Gets from the cache
	 */
	protected function getFromCache( $uri )
	{
		$parserCache =& ParserCache::singleton();
		return $parserCache->mMemc->get( $uri );
	}
	/**
	 * Saves in the cache
	 */
	protected function saveInCache( $uri, &$text, $timeout )
	{
		$parserCache =& ParserCache::singleton();
		
		// keep in cache a little longer to give time to the HEAD lookup 
		// to determine if the source page has really changed
		return $parserCache->mMemc->set( $uri, $text, $timeout );
	}
	/**
	 *  Fetches an external page from either the parser cache or external uri
	 */	
	protected function fetch( $uri, $timeout )
	{
		// just encode the string to make sure
		// we don't break anything downstream.
		$euri = urlencode( $uri );
		
		// try to fetch from cache
		$text = $this->getFromCache( $euri );
		if ( $text === false)
		{
			$text = Http::get( $uri, $timeout );
			if ( $text !== false )
				$this->saveInCache( $euri, $text, $timeout + 86400 /*1day*/  );
		}
		
		return $text;
	}
	/**
	 *
	 */
	protected function processFetch()
	{
		
	}	 
	/**
	 * Get E-tag
	 * Requires pecl module ''pecl_http''
	 *
	 */
	protected function getEtag( &$uri )
	{
		if (!function_exists('http_head'))
			return null;

		$head = @http_head( $uri );
		if ( $head === false )
			return false;
			
		$r = preg_match( "/Etag:.\"(.*)\"\n/i", $head, $match );
		if ( ( $r === false ) || ($r === 0) )
			return false;
			
		return $match[1];
	}
	/**
	 * Save Etag in cache
	 */
	protected function saveEtagInCache( &$uri, &$etag )
	{
		return $this->saveInCache( $uri.'-etag', $etag, 86400 /*1day*/ );
	}
	/**
	 * Get Etag from the cache
	 */
	protected function getEtagFromCache( &$uri )
	{
		return $this->getFromCache( $uri.'-etag' );
	}	 
} // end class
//</source>
