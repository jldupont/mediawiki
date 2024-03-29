<?php
/**
 * Page history
 *
 * Split off from Article.php and Skin.php, 2003-12-22
 * @package MediaWiki
 */

/**
 * This class handles printing the history page for an article.  In order to
 * be efficient, it uses timestamps rather than offsets for paging, to avoid
 * costly LIMIT,offset queries.
 *
 * Construct it by passing in an Article, and call $h->history() to print the
 * history.
 *
 * @package MediaWiki
 */

class PageHistory {
	const DIR_PREV = 0;
	const DIR_NEXT = 1;
	
	var $mArticle, $mTitle, $mSkin;
	var $lastdate;
	var $linesonpage;
	var $mNotificationTimestamp;
	var $mLatestId = null;

	/**
	 * Construct a new PageHistory.
	 *
	 * @param Article $article
	 * @returns nothing
	 */
	function PageHistory($article) {
		global $wgUser;

		$this->mArticle =& $article;
		$this->mTitle =& $article->mTitle;
		$this->mNotificationTimestamp = NULL;
		$this->mSkin = $wgUser->getSkin();
	}

	/**
	 * Print the history page for an article.
	 *
	 * @returns nothing
	 */
	function history() {
		global $wgOut, $wgRequest, $wgTitle;

		/*
		 * Allow client caching.
		 */

		if( $wgOut->checkLastModified( $this->mArticle->getTimestamp() ) )
			/* Client cache fresh and headers sent, nothing more to do. */
			return;

		$fname = 'PageHistory::history';
		wfProfileIn( $fname );

		/*
		 * Setup page variables.
		 */
		$wgOut->setPageTitle( $this->mTitle->getPrefixedText() );
		$wgOut->setArticleFlag( false );
		$wgOut->setArticleRelated( true );
		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		$wgOut->setSyndicated( true );

		$logPage = Title::makeTitle( NS_SPECIAL, 'Log' );
		$logLink = $this->mSkin->makeKnownLinkObj( $logPage, wfMsgHtml( 'viewpagelogs' ), 'page=' . $this->mTitle->getPrefixedUrl() );

		$subtitle = wfMsgHtml( 'revhistory' ) . '<br />' . $logLink;
		$wgOut->setSubtitle( $subtitle );

		$feedType = $wgRequest->getVal( 'feed' );
		if( $feedType ) {
			wfProfileOut( $fname );
			return $this->feed( $feedType );
		}

		/*
		 * Fail if article doesn't exist.
		 */
		if( !$this->mTitle->exists() ) {
			$wgOut->addWikiText( wfMsg( 'nohistory' ) );
			wfProfileOut( $fname );
			return;
		}

		
		/*
		 * "go=first" means to jump to the last (earliest) history page.
		 * This is deprecated, it no longer appears in the user interface
		 */
		if ( $wgRequest->getText("go") == 'first' ) {
			$limit = $wgRequest->getInt( 'limit', 50 );
			$wgOut->redirect( $wgTitle->getLocalURL( "action=history&limit={$limit}&dir=prev" ) );
			return;
		}
// JLD BEGIN PATCH (code from MW 1.10)
		wfRunHooks( 'PageHistoryBeforeList', array( &$this->mArticle ) );
// JLD END PATCH

		/** 
		 * Do the list
		 */
		$pager = new PageHistoryPager( $this );
		$navbar = $pager->getNavigationBar();
		$this->linesonpage = $pager->getNumRows();
		$wgOut->addHTML(
			$pager->getNavigationBar() . 
			$this->beginHistoryList() . 
			$pager->getBody() . 
			$this->endHistoryList() .
			$pager->getNavigationBar()
		);
		wfProfileOut( $fname );
	}

	/** @todo document */
	function beginHistoryList() {
		global $wgTitle;
		$this->lastdate = '';
		$s = wfMsgExt( 'histlegend', array( 'parse') );
		$s .= '<form action="' . $wgTitle->escapeLocalURL( '-' ) . '" method="get">';
		$prefixedkey = htmlspecialchars($wgTitle->getPrefixedDbKey());

		// The following line is SUPPOSED to have double-quotes around the
		// $prefixedkey variable, because htmlspecialchars() doesn't escape
		// single-quotes.
		//
		// On at least two occasions people have changed it to single-quotes,
		// which creates invalid HTML and incorrect display of the resulting
		// link.
		//
		// Please do not break this a third time. Thank you for your kind
		// consideration and cooperation.
		//
		$s .= "<input type='hidden' name='title' value=\"{$prefixedkey}\" />\n";

		$s .= $this->submitButton();
		$s .= '<ul id="pagehistory">' . "\n";
		return $s;
	}

	/** @todo document */
	function endHistoryList() {
		$s = '</ul>';
		$s .= $this->submitButton( array( 'id' => 'historysubmit' ) );
		$s .= '</form>';
		return $s;
	}

	/** @todo document */
	function submitButton( $bits = array() ) {
		return ( $this->linesonpage > 0 )
			? wfElement( 'input', array_merge( $bits,
				array(
					'class'     => 'historysubmit',
					'type'      => 'submit',
					'accesskey' => wfMsg( 'accesskey-compareselectedversions' ),
					'title'     => wfMsg( 'tooltip-compareselectedversions' ),
					'value'     => wfMsg( 'compareselectedversions' ),
				) ) )
			: '';
	}

	/** @todo document */
	function historyLine( $row, $next, $counter = '', $notificationtimestamp = false, $latest = false, $firstInList = false ) {
		global $wgUser;
		$rev = new Revision( $row );
		$rev->setTitle( $this->mTitle );

		$s = '<li>';
		$curlink = $this->curLink( $rev, $latest );
		$lastlink = $this->lastLink( $rev, $next, $counter );
		$arbitrary = $this->diffButtons( $rev, $firstInList, $counter );
		$link = $this->revLink( $rev );
		
		$user = $this->mSkin->userLink( $rev->getUser(), $rev->getUserText() )
				. $this->mSkin->userToolLinks( $rev->getUser(), $rev->getUserText() );
		
		$s .= "($curlink) ($lastlink) $arbitrary";
		
		if( $wgUser->isAllowed( 'deleterevision' ) ) {
			$revdel = Title::makeTitle( NS_SPECIAL, 'Revisiondelete' );
			if( $firstInList ) {
				// We don't currently handle well changing the top revision's settings
				$del = wfMsgHtml( 'rev-delundel' );
			} else {
				$del = $this->mSkin->makeKnownLinkObj( $revdel,
					wfMsg( 'rev-delundel' ),
					'target=' . urlencode( $this->mTitle->getPrefixedDbkey() ) .
					'&oldid=' . urlencode( $rev->getId() ) );
			}
			$s .= "(<small>$del</small>) ";
		}
		
		$s .= " $link <span class='history-user'>$user</span>";

		if( $row->rev_minor_edit ) {
			$s .= ' ' . wfElement( 'span', array( 'class' => 'minor' ), wfMsg( 'minoreditletter') );
		}

		$s .= $this->mSkin->revComment( $rev );
		if ($notificationtimestamp && ($row->rev_timestamp >= $notificationtimestamp)) {
			$s .= ' <span class="updatedmarker">' .  wfMsgHtml( 'updatedmarker' ) . '</span>';
		}
		if( $row->rev_deleted & Revision::DELETED_TEXT ) {
			$s .= ' ' . wfMsgHtml( 'deletedrev' );
		}
		$s .= "</li>\n";

		return $s;
	}
	
	/** @todo document */
	function revLink( $rev ) {
		global $wgLang;
		$date = $wgLang->timeanddate( wfTimestamp(TS_MW, $rev->getTimestamp()), true );
		if( $rev->userCan( Revision::DELETED_TEXT ) ) {
			$link = $this->mSkin->makeKnownLinkObj(
				$this->mTitle, $date, "oldid=" . $rev->getId() );
		} else {
			$link = $date;
		}
		if( $rev->isDeleted( Revision::DELETED_TEXT ) ) {
			return '<span class="history-deleted">' . $link . '</span>';
		}
		return $link;
	}

	/** @todo document */
	function curLink( $rev, $latest ) {
		$cur = wfMsgExt( 'cur', array( 'escape') );
		if( $latest || !$rev->userCan( Revision::DELETED_TEXT ) ) {
			return $cur;
		} else {
			return $this->mSkin->makeKnownLinkObj(
				$this->mTitle, $cur,
				'diff=' . $this->getLatestID() .
				"&oldid=" . $rev->getId() );
		}
	}

	/** @todo document */
	function lastLink( $rev, $next, $counter ) {
		$last = wfMsgExt( 'last', array( 'escape' ) );
		if ( is_null( $next ) ) {
			# Probably no next row
			return $last;
		} elseif ( $next === 'unknown' ) {
			# Next row probably exists but is unknown, use an oldid=prev link
			return $this->mSkin->makeKnownLinkObj(
				$this->mTitle,
				$last,
				"diff=" . $rev->getId() . "&oldid=prev" );
		} elseif( !$rev->userCan( Revision::DELETED_TEXT ) ) {
			return $last;
		} else {
			return $this->mSkin->makeKnownLinkObj(
				$this->mTitle,
				$last,
				"diff=" . $rev->getId() . "&oldid={$next->rev_id}"
				/*,
				'',
				'',
				"tabindex={$counter}"*/ );
		}
	}

	/** @todo document */
	function diffButtons( $rev, $firstInList, $counter ) {
		if( $this->linesonpage > 1) {
			$radio = array(
				'type'  => 'radio',
				'value' => $rev->getId(),
# do we really need to flood this on every item?
#				'title' => wfMsgHtml( 'selectolderversionfordiff' )
			);

			if( !$rev->userCan( Revision::DELETED_TEXT ) ) {
				$radio['disabled'] = 'disabled';
			}

			/** @todo: move title texts to javascript */
			if ( $firstInList ) {
				$first = wfElement( 'input', array_merge(
					$radio,
					array(
						'style' => 'visibility:hidden',
						'name'  => 'oldid' ) ) );
				$checkmark = array( 'checked' => 'checked' );
			} else {
				if( $counter == 2 ) {
					$checkmark = array( 'checked' => 'checked' );
				} else {
					$checkmark = array();
				}
				$first = wfElement( 'input', array_merge(
					$radio,
					$checkmark,
					array( 'name'  => 'oldid' ) ) );
				$checkmark = array();
			}
			$second = wfElement( 'input', array_merge(
				$radio,
				$checkmark,
				array( 'name'  => 'diff' ) ) );
			return $first . $second;
		} else {
			return '';
		}
	}

	/** @todo document */
	function getLatestId() {
		if( is_null( $this->mLatestId ) ) {
			$id = $this->mTitle->getArticleID();
			$db =& wfGetDB(DB_SLAVE);
			$this->mLatestId = $db->selectField( 'page',
				"page_latest",
				array( 'page_id' => $id ),
				'PageHistory::getLatestID' );
		}
		return $this->mLatestId;
	}

	/**
	 * Fetch an array of revisions, specified by a given limit, offset and
	 * direction. This is now only used by the feeds. It was previously 
	 * used by the main UI but that's now handled by the pager.
	 */
	function fetchRevisions($limit, $offset, $direction) {
		$fname = 'PageHistory::fetchRevisions';

		$dbr =& wfGetDB( DB_SLAVE );

		if ($direction == PageHistory::DIR_PREV)
			list($dirs, $oper) = array("ASC", ">=");
		else /* $direction == PageHistory::DIR_NEXT */
			list($dirs, $oper) = array("DESC", "<=");

		if ($offset)
			$offsets = array("rev_timestamp $oper '$offset'");
		else
			$offsets = array();

		$page_id = $this->mTitle->getArticleID();

		$res = $dbr->select(
			'revision',
			array('rev_id', 'rev_page', 'rev_text_id', 'rev_user', 'rev_comment', 'rev_user_text',
				'rev_timestamp', 'rev_minor_edit', 'rev_deleted'),
			array_merge(array("rev_page=$page_id"), $offsets),
			$fname,
			array('ORDER BY' => "rev_timestamp $dirs",
				'USE INDEX' => 'page_timestamp', 'LIMIT' => $limit)
			);

		$result = array();
		while (($obj = $dbr->fetchObject($res)) != NULL)
			$result[] = $obj;

		return $result;
	}

	/** @todo document */
	function getNotificationTimestamp() {
		global $wgUser, $wgShowUpdatedMarker;
		$fname = 'PageHistory::getNotficationTimestamp';

		if ($this->mNotificationTimestamp !== NULL)
			return $this->mNotificationTimestamp;

		if ($wgUser->isAnon() || !$wgShowUpdatedMarker)
			return $this->mNotificationTimestamp = false;

		$dbr =& wfGetDB(DB_SLAVE);

		$this->mNotificationTimestamp = $dbr->selectField(
			'watchlist',
			'wl_notificationtimestamp',
			array(	'wl_namespace' => $this->mTitle->getNamespace(),
				'wl_title' => $this->mTitle->getDBkey(),
				'wl_user' => $wgUser->getID()
			),
			$fname);
		
		// Don't use the special value reserved for telling whether the field is filled
		if ( is_null( $this->mNotificationTimestamp ) ) {
			$this->mNotificationTimestamp = false;
		}

		return $this->mNotificationTimestamp;
	}
	
	/**
	 * Output a subscription feed listing recent edits to this page.
	 * @param string $type
	 */
	function feed( $type ) {
		require_once 'SpecialRecentchanges.php';
		
		global $wgFeedClasses;
		if( !isset( $wgFeedClasses[$type] ) ) {
			global $wgOut;
			$wgOut->addWikiText( wfMsg( 'feed-invalid' ) );
			return;
		}
		
		$feed = new $wgFeedClasses[$type](
			$this->mTitle->getPrefixedText() . ' - ' .
				wfMsgForContent( 'history-feed-title' ),
			wfMsgForContent( 'history-feed-description' ),
			$this->mTitle->getFullUrl( 'action=history' ) );

		$items = $this->fetchRevisions(10, 0, PageHistory::DIR_NEXT);
		$feed->outHeader();
		if( $items ) {
			foreach( $items as $row ) {
				$feed->outItem( $this->feedItem( $row ) );
			}
		} else {
			$feed->outItem( $this->feedEmpty() );
		}
		$feed->outFooter();
	}
	
	function feedEmpty() {
		global $wgOut;
		return new FeedItem(
			wfMsgForContent( 'nohistory' ),
			$wgOut->parse( wfMsgForContent( 'history-feed-empty' ) ),
			$this->mTitle->getFullUrl(),
			wfTimestamp( TS_MW ),
			'',
			$this->mTitle->getTalkPage()->getFullUrl() );
	}
	
	/**
	 * Generate a FeedItem object from a given revision table row
	 * Borrows Recent Changes' feed generation functions for formatting;
	 * includes a diff to the previous revision (if any).
	 *
	 * @param $row
	 * @return FeedItem
	 */
	function feedItem( $row ) {
		$rev = new Revision( $row );
		$rev->setTitle( $this->mTitle );
		$text = rcFormatDiffRow( $this->mTitle,
			$this->mTitle->getPreviousRevisionID( $rev->getId() ),
			$rev->getId(),
			$rev->getTimestamp(),
			$rev->getComment() );
		
		if( $rev->getComment() == '' ) {
			global $wgContLang;
			$title = wfMsgForContent( 'history-feed-item-nocomment',
				$rev->getUserText(),
				$wgContLang->timeanddate( $rev->getTimestamp() ) );
		} else {
			$title = $rev->getUserText() . ": " . $this->stripComment( $rev->getComment() );
		}

		return new FeedItem(
			$title,
			$text,
			$this->mTitle->getFullUrl( 'diff=' . $rev->getId() . '&oldid=prev' ),
			$rev->getTimestamp(),
			$rev->getUserText(),
			$this->mTitle->getTalkPage()->getFullUrl() );
	}
	
	/**
	 * Quickie hack... strip out wikilinks to more legible form from the comment.
	 */
	function stripComment( $text ) {
		return preg_replace( '/\[\[([^]]*\|)?([^]]+)\]\]/', '\2', $text );
	}
}


class PageHistoryPager extends ReverseChronologicalPager {
	public $mLastRow = false, $mPageHistory;
	
	function __construct( $pageHistory ) {
		parent::__construct();
		$this->mPageHistory = $pageHistory;
	}

	function getQueryInfo() {
		return array(
			'tables' => 'revision',
			'fields' => array('rev_id', 'rev_page', 'rev_text_id', 'rev_user', 'rev_comment', 'rev_user_text',
				'rev_timestamp', 'rev_minor_edit', 'rev_deleted'),
			'conds' => array('rev_page' => $this->mPageHistory->mTitle->getArticleID() ),
			'options' => array( 'USE INDEX' => 'page_timestamp' )
		);
	}

	function getIndexField() {
		return 'rev_timestamp';
	}

	function formatRow( $row ) {
		if ( $this->mLastRow ) {
			$latest = $this->mCounter == 1 && $this->mOffset == '';
			$firstInList = $this->mCounter == 1;
			$s = $this->mPageHistory->historyLine( $this->mLastRow, $row, $this->mCounter++, 
				$this->mPageHistory->getNotificationTimestamp(), $latest, $firstInList );
		} else {
			$s = '';
		}
		$this->mLastRow = $row;
		return $s;
	}
	
	function getStartBody() {
		$this->mLastRow = false;
		$this->mCounter = 1;
		return '';
	}

	function getEndBody() {
		if ( $this->mLastRow ) {
			$latest = $this->mCounter == 1 && $this->mOffset == 0;
			$firstInList = $this->mCounter == 1;
			if ( $this->mIsBackwards ) {
				# Next row is unknown, but for UI reasons, probably exists if an offset has been specified
				if ( $this->mOffset == '' ) {
					$next = null;
				} else {
					$next = 'unknown';
				}
			} else {
				# The next row is the past-the-end row
				$next = $this->mPastTheEndRow;
			}
			$s = $this->mPageHistory->historyLine( $this->mLastRow, $next, $this->mCounter++, 
				$this->mPageHistory->getNotificationTimestamp(), $latest, $firstInList );
		} else {
			$s = '';
		}
		return $s;
	}
}

?>
