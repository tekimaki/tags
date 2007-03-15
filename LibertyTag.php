<?php

require_once( KERNEL_PKG_PATH.'BitBase.php' );

class LibertyTag extends LibertyBase {
	var $mContentId;

	function LibertyTag( $pContentId=NULL ) {
		LibertyBase::LibertyBase();
		$this->mContentId = $pContentId;
	}


	/* Delete when package complete! -wjames5
	 * methods needed
	 *
	 * get all tags limited by content_id		<- load
	 * get all tags								<- getList
	 * get all content for tag_id				<- getContentList
	 * get tags by map use count				<- getList with map refs count
	 * get tags	sorted by tagged date			<- getList sorted by map tagged date
	 *
	 * store new tags from tags array
	 * store new tag-content map for content_id
	 * expunge tag and all tag-content maps
	 * expunge tag-content map for content_id
	 *
	 */


	/**
	* Load all the tags for a given ContentId
	* @param pParamHash be sure to pass by reference in case we need to make modifcations to the hash
	**/
	function load() {
		if( $this->isValid() ) {
			$query = "
					SELECT tgc.*, tg.* 
					FROM `".BIT_DB_PREFIX."tags_content_map` tgc
						INNER JOIN `".BIT_DB_PREFIX."tags` tg ON tg.`tag_id` = tgc.`tag_id`
					WHERE tgc.`content_id`=?";					
			$this->mInfo = $this->mDb->getRow( $query, array( $this->mContentId ) );
		}
		return( count( $this->mInfo ) );
	}

	/**
	* Make sure the data is safe to store
	* @param array pParams reference to hash of values that will be used to store the page, they will be modified where necessary
	* @return bool TRUE on success, FALSE if verify failed. If FALSE, $this->mErrors will have reason why
	* @access private
	**/
	function verify( &$pParamHash ) {
		$pParamHash['tag_store'] = array();
		$pParamHash['tag_map_store'] = array();

		if(!empty( $pParamHash['tag'])){	
			$pParamHash['tag_store']['tag'] = $pParamHash['tag'];			
		}
		if( !empty( $pParamHash['tag_id']) && is_numeric( $pParamHash['tag_id'])){	
			$pParamHash['tag_map_store']['tag_id'] = $pParamHash['tag_id'];			
		}
		if( isset( $pParamHash['tagged_on']) ){	
			$pParamHash['tag_map_store']['tagged_on'] = $pParamHash['tagged_on'];			
		} else {
			$pParamHash['tag_map_store']['tagged_on'] = $gBitSystem->getUTCTime();
		}
		if( @$this->verifyId( $pParamHash['content_id']) ){	
			$pParamHash['tag_map_store']['content_id'] = $pParamHash['content_id'];			
		} else {
			$this->mErrors['content_id'] = "No content id specified.";
		}
		// is this the best way to associate a user_id? should it even be included? -wjames5
		if( @$this->verifyId( $pParamHash['user_id']) ){	
			$pParamHash['tag_map_store']['tagger_id'] = $pParamHash['user_id'];			
		} else {
			$this->mErrors['user_id'] = "No user id specified.";
		}
		
		return( count( $this->mErrors )== 0 );
	}
	
	
	/* check tag exists
	 */
	function verifyTag ( &$pParamHash ){
		$ret = FALSE;
		
		$selectSql = ''; $joinSql = ''; $whereSql = '';	
		$bindVars = array();
		// if tag_id supplied, use that
		if( !empty( $pParamHash['tag_id'] ) && is_numeric( $pParamHash['tag_id'] )) {		
			$whereSql .= "WHERE tg.`tag_id` = ?";
			$bindVars .= $pParamHash['tag_id'];
		}elseif( isset( $pParamHash['tag_'] ) ) {
			$whereSql .= "WHERE tg.`tag` = ?";
			$bindVars .= $pParamHash['tag'];
		}
		
		$query = "
				SELECT tg.* 
				FROM `".BIT_DB_PREFIX."tags` tg
				$whereSql";
		if ( $result = $this->mDb->getRow( $query, $bindVars ) ){
			$pParamHash['tag_id'] = $result['tag_id'];
			$this->mTagId = $result['tag'];
			$ret = TRUE;
		};
		
		return $ret;
	}

	
	
	/**
	* @param array pParams hash of values that will be used to store the page
	* @return bool TRUE on success, FALSE if store could not occur. If FALSE, $this->mErrors will have reason why
	* @access public
	**/
	function store( &$pParamHash ) {
		if( $this->verify( $pParamHash ) ) {
			$this->mDb->StartTrans();
			if (!empty($pParamHash['tag_store'])) {
				$tagtable = BIT_DB_PREFIX."tags"; 
				$maptable = BIT_DB_PREFIX."tags_content_map";
				$this->mDb->StartTrans();				
				
				if( $this->verifyTag($pParamHash['tag_map_store'])) {
						$this->mDb->associateInsert( $maptable, $pParamHash['tag_map_store'] );
				} else {
					$pParamHash['tag_store']['tag_id'] = $this->mDb->GenID( 'tags_tag_id_seq' );
					if ( $this->mDb->associateInsert( $tagtable, $pParamHash['tag_store'] ) ){
						$this->mTagId = $pParamHash['tag_map_store']['tag_id'] = $pParamHash['tag_store']['tag_id'];
						$this->mDb->associateInsert( $maptable, $pParamHash['tag_map_store'] );
					}
				}
			}
			$this->mDb->CompleteTrans();
			// since we use store generally in a loop of several tags we should not load here
			//$this->load();
		}
		return( count( $this->mErrors )== 0 );
	}


	/* make tag data is safe to store
	 */
	function verifyTagsMap( &$pParamHash ) {
		global $gBitUser, $gBitSystem;

		$pParamHash['map_store'] = array();
		
		//this is to set the time we add content to a tag.
		$timeStamp = $gBitSystem->getUTCTime();
		
		$tagMixed = $pParamHash['tags']; //need to break up this string
		if( !empty( $tagMixed )){
			if (!is_array( $tagMixed ) && !is_numeric( $tagMixed ) ){
				$tagIds = explode( ",", $tagMixed );
			}else if ( is_array( $tagMixed ) ) {
				$tagIds = $tagMixed;
			}else if ( is_numeric( $tagMixed ) ) {
				$tagIds = array( $tagMixed );
			}
		}
	
		foreach( $tagIds as $value ) {
			//how do we sanitize tags here? -wjames5
			if( !empty($value) ) {
				array_push( $pParamHash['map_store'], array( 
					'tag' => $value, 
					'tagged_on' => $timeStamp,
					'content_id' => $this->mContentId, 
					'user_id' => $gBitUser->mUserId, 
				));
			} else {
				$this->mErrors[$value] = "Invalid tag.";
			}
		}		

		return ( count( $this->mErrors ) == 0 );
	}
	
	
	/**
	* @param array pParams hash includes mix of tags that will be storeded and associated with a ContentId used by service
	* @return bool TRUE on success, FALSE if store could not occur. If FALSE, $this->mErrors will have reason why
	* @access public
	**/
	function storeTags( &$pParamHash ){
		global $gBitSystem;
		if( $this->verifyTagsMap( $pParamHash ) ) {
			if( $this->isValid() ) {
				foreach ( $pParamHash['map_store'] as $value) {
					$result = $this->store( $value );					
				}
				$this->load();
			}
		}
		return ( count( $this->mErrors ) == 0 );
	}


	/**
	* check if the mContentId is set and valid
	*/
	function isValid() {
		return( @BitBase::verifyId( $this->mContentId ) );
	}

	/**
	* This function removes a tag entry
	**/
	function expunge( $tag_id ) {
		$ret = FALSE;
		if( $this->isValid() ) {
			$query = "DELETE FROM `".BIT_DB_PREFIX."tags` WHERE `tag_id` = ?";
			$result = $this->mDb->query( $query, array( $tag_id ) );
			
			// remove all references to tag in tags_content_map
			$query_map = "DELETE FROM `".BIT_DB_PREFIX."tags_content_map` WHERE `tag_id` = ?";			
			$result = $this->mDb->query( $query_map, array( $tag_id ) );			
		}
		return $ret;
	}

	/**
	* This function removes all references to contentid from tags_content_map
	**/
	function expungeContentFromTagMap(){
		$ret = FALSE;
		if( $this->isValid() ) {
			$query = "DELETE FROM `".BIT_DB_PREFIX."tags_content_map` WHERE `content_id` = ?";			
			$result = $this->mDb->query( $query, array( $this->mContentId ) );			
		}
		return $ret;
	}
	
	/**
	* This function gets a list of tags
	**/
	function getList( &$pParamHash ) {
		global $gBitUser, $gBitSystem;

		$selectSql = ''; $joinSql = ''; $whereSql = '';	
		$bindVars = array();

		$sort_mode_prefix = 'lc';
		if( empty( $pParamHash['sort_mode'] ) ) {
			$pParamHash['sort_mode'] = 'title_desc';
		}

		/**
		* @TODO this all needs to go in in some other getList type method
		* and these are just sketches - need to be different kinds of queries in most cases
		**/
		/*
		// get tags by most hits on content
		if ($pParamHash['sort_mode'] == 'hits_desc') {
			$joinSql .=	"LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content_hits` lch ON lc.`content_id`         = lch.`content_id`";
		}

		// get tags	sorted by tagged date			<- getList sorted by map tagged date
		if ($pParamHash['sort_mode'] == 'tagged_on_desc') {
			$sort_mode_prefix = 'tgc';
		}	
		*/

		$sort_mode = $sort_mode_prefix . '.' . $this->mDb->convertSortmode( $pParamHash['sort_mode'] ); 

		// get all tags
		$query = "
			SELECT tg.*
				$selectSql
			FROM `".BIT_DB_PREFIX."tags` tg
				$joinSql
			ORDER BY $sort_mode";

		$query_cant = "
			SELECT COUNT( * ) 
			FROM `".BIT_DB_PREFIX."tags` tg
				$joinSql";
		
		$result = $this->mDb->query($query,$bindVars,$pParamHash['max_records'],$pParamHash['offset']);
		$cant = $this->mDb->getOne($query_cant,$bindVars);
		$ret = array();

		$comment = &new LibertyComment();
		while ($res = $result->fetchRow()) {
			$res['popcant'] = $this->getPopCount($res['tag_id']);
			$ret[] = $res;
		}
		
		//if the user has asked to sort the tags by use we sort the array before returning it
		if ($pParamHash['sort_mode'] == 'cant_desc') {
			foreach ($ret as $key => $row) {
			   $popcant[$key]  = $row['popcant'];
			}			
			array_multisort($popcant, SORT_DESC, $ret);		
		}
		
		$pParamHash["data"] = $ret;
		$pParamHash["cant"] = $cant;

		return $pParamHash;
	}	
	
	/**
	* This function gets the number of times a tag is used aka Popularity Count
	**/
	function getPopCount($tag_id){
		$query_cant = "
			SELECT COUNT( * ) 
			FROM `".BIT_DB_PREFIX."tags_content_map` tgc
			WHERE tgc.`tag_id` = ?";
		$cant = $this->mDb->getOne($query_cant, array($tag_id) );
	}
	
}

/********* SERVICE FUNCTIONS *********/
function tags_content_display( &$pObject ) {
	global $gBitSystem, $gBitSmarty, $gBitUser, $gPreviewStyle;
	if ( $gBitSystem->isPackageActive( 'tags' ) ) {
		$tag = new LibertyTag( $pObject->mContentId );
		if( $gBitUser->hasPermission( 'p_tags_view' ) ) {		
			if( $tags = $tag->load() ) {
				//loop through results and piece together tags.
				$tagData = "";
				$count = sizeof($tags);
				for($n=0; $n<$count; $n++){
					$tagData .= $tags[$n]['tag'];
					$tagData .= ($n < $count-1)? ", ":"";
				}
				$gBitSmarty->assign( 'tagData', !empty( $tagData ) ? $tagData : FALSE );
			}
		}
	}	
}

/**
 * filter the search with pigeonholes
 * @param $pParamHash['tags']['filter'] - a tag or an array of tags
 **/
/*
function tags_content_list_sql( &$pObject, $pParamHash = NULL ) {
	global $gBitSystem;
	$ret = array();
	if( !empty( $pParamHash['tags']['filter'] ) ) {
		if ( is_array( $pParamHash['tags']['filter'] ) ) {
			$ret['join_sql'] = "LEFT JOIN `".BIT_DB_PREFIX."tag_members` pm ON (lc .`content_id`= pm.`content_id`)";
			$ret['where_sql'] = ' AND pm.`parent_id` in ('.implode( ',', array_fill(0, count( $pParamHash['tags']['filter']  ), '?' ) ).')';
			$ret['bind_vars'] = $pParamHash['tags']['filter'];
		} else {
			$ret['join_sql'] = "LEFT JOIN `".BIT_DB_PREFIX."tag_members` pm ON (lc .`content_id`= pm.`content_id`)";
			$ret['where_sql'] = " AND pm.`parent_id`=? ";
			$ret['bind_vars'][] = $pParamHash['tags']['filter'];
		}
	}
	if( !empty( $pParamHash['liberty_categories'] ) ) {
			$ret['join_sql'] = "LEFT JOIN `".BIT_DB_PREFIX."tag_members` pm ON (lc .`content_id`= pm.`content_id`)";
		if ( is_array( $pParamHash['liberty_categories'] ) ) {
			$ret['where_sql'] = ' AND pm.`parent_id` in ('.implode( ',', array_fill(0, count( $pParamHash['liberty_categories']  ), '?' ) ).')';
			$ret['bind_vars'] = $pParamHash['liberty_categories'];
		} else {
			$ret['where_sql'] = " AND pm.`parent_id`=? ";
			$ret['bind_vars'][] = $pParamHash['liberty_categories'];
		}
	}
	return $ret;
}
*/

/**
 * @param includeds a string or array of 'tags' and contentid for association.
 **/
function tags_content_store( &$pObject, &$pParamHash ) {
	global $gBitSystem;
	$errors = NULL;
	// If a content access system is active, let's call it
	if( $gBitSystem->isPackageActive( 'tags' ) ) {
		$tag = new LibertyTag( $pObject->mContentId );
		if ( !$tag->storeTags( $pParamHash ) ) {
			$errors=$tag->mErrors;
		}
	}
	return( $errors );
}

function tags_content_preview( &$pObject) {
	global $gBitSystem;
	if ( $gBitSystem->isPackageActive( 'tags' ) ) {		
		if (isset($_REQUEST['tags'])) {
			$pObject->mInfo['tags'] = $_REQUEST['tags'];
		}
	}
}

function tags_content_expunge( &$pObject ) {
	$tag = new LibertyTag( $pObject->mContentId );
	$tag->expungeContentFromTagMap();
}
?>
