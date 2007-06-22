<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_tags/LibertyTag.php,v 1.31 2007/06/22 11:15:08 lsces Exp $
 * @package tags
 * 
 * @copyright Copyright (c) 2004-2006, bitweaver.org
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
 */

/**
 * required setup
 */
require_once( KERNEL_PKG_PATH.'BitBase.php' );

/**
 * @package tags
 */
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

			//$this->mInfo = $this->mDb->query( $query, array( $this->mContentId ) );
			$result = $this->mDb->query( $query, array( $this->mContentId ) );
			if ($result) {
				$ret = array();
				while ($res = $result->fetchRow()) {
					$ret[] = $res;
				}
				$this->mInfo['tags'] = $ret;
			}
		}
		return( count( $this->mInfo ) );
	}


	function loadTag ( &$pParamHash ){
		if( !empty( $pParamHash['tag_id'] ) && is_numeric( $pParamHash['tag_id'] )) {
			$selectSql = ''; $joinSql = ''; $whereSql = '';
			$bindVars = array();

			$whereSql .= "WHERE tg.`tag_id` = ?";
			$bindVars[] = $pParamHash['tag_id'];

			$query = "
					SELECT tg.*
					FROM `".BIT_DB_PREFIX."tags` tg
					$whereSql";

			if ( $result = $this->mDb->getRow( $query, $bindVars ) ){
				$this->mInfo = $result;
			};
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
		global $gBitUser, $gBitSystem;
		$pParamHash['tag_store'] = array();
		$pParamHash['tag_map_store'] = array();

		if(!empty( $pParamHash['tag'])){
			$pParamHash['tag_store']['tag'] = $pParamHash['tag'];
		}
		if( !empty( $pParamHash['tag_id']) && is_numeric( $pParamHash['tag_id'])){
//			$pParamHash['tag_map_store']['tag_id'] = $pParamHash['tag_id'];
			$pParamHash['tag_store']['tag_id'] = $pParamHash['tag_id'];
		}
		if( isset( $pParamHash['tagged_on']) ){
			$pParamHash['tag_map_store']['tagged_on'] = $pParamHash['tagged_on'];
		} else {
			$pParamHash['tag_map_store']['tagged_on'] = $gBitSystem->getUTCTime();
		}
		if( @$this->verifyId( $pParamHash['content_id']) ){
			$pParamHash['tag_map_store']['content_id'] = $pParamHash['content_id'];
		}/* else {
			$this->mErrors['content_id'] = "No content id specified.";
		}*/
		if( $gBitUser->mUserId ){
			$pParamHash['tag_map_store']['tagger_id'] = $gBitUser->mUserId;
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
			$bindVars[] = $pParamHash['tag_id'];
		}elseif( isset( $pParamHash['tag'] ) ) {
			$whereSql .= "WHERE tg.`tag` = ?";
			$bindVars[] = $pParamHash['tag'];
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

				if( $this->verifyTag($pParamHash['tag_store']) ) {
					$pParamHash['tag_map_store']['tag_id'] = $pParamHash['tag_store']['tag_id'];
					$this->mDb->associateInsert( $maptable, $pParamHash['tag_map_store'] );
				} else {
					$pParamHash['tag_store']['tag_id'] = $this->mDb->GenID( 'tags_tag_id_seq' );
					$this->mDb->associateInsert( $tagtable, $pParamHash['tag_store'] );
					$this->mTagId = $pParamHash['tag_map_store']['tag_id'] = $pParamHash['tag_store']['tag_id'];
					$this->mDb->associateInsert( $maptable, $pParamHash['tag_map_store'] );
				}
			}
			$this->mDb->CompleteTrans();
			// since we use store generally in a loop of several tags we should not load here
			//$this->load();
		}
		return( count( $this->mErrors )== 0 );
	}



	function storeOneTag( &$pParamHash ) {
		if( $this->verify( $pParamHash ) ) {
			$this->mDb->StartTrans();
			if (!empty($pParamHash['tag_store'])) {
				$tagtable = BIT_DB_PREFIX."tags";

				if( isset($pParamHash['tag_store']['tag_id']) ) {
					//this is kind of ugly but it works right
					$this->mDb->associateUpdate( $tagtable, array("tag" => $pParamHash['tag_store']['tag']),  array( "tag_id" => $pParamHash['tag_id'] )  );
				} else {
					$pParamHash['tag_store']['tag_id'] = $this->mDb->GenID( 'tags_tag_id_seq' );
					$this->mDb->associateInsert( $tagtable, $pParamHash['tag_store'] );
				}
			}
			$this->mDb->CompleteTrans();
			$this->loadTag( $pParamHash['tag_store'] );
		}
		return( count( $this->mErrors )== 0 );
	}

	function sanitizeTag($pTag) {
		global $gBitSystem;

		// We always trim tags
		$pTag = trim($pTag);

		if( $gBitSystem->isFeatureActive("tags_strip_spaces") ) {
			$pTag = preg_replace('/\s+/', '', $pTag);
		}

		if( $gBitSystem->isFeatureActive("tags_strip_nonword") ) {
			$pTag  = preg_replace('/\W+/', '', $pTag);
		}

		if( $gBitSystem->isFeatureActive("tags_lowercase") ) {
			$pTag = strtolower($pTag);
		}

		if( $gBitSystem->getConfig('tags_strip_regexp') ) {
			$pTag = preg_replace($gBitSystem->getConfig('tags_strip_regexp', $pTag), $gBitSystem->getConfig('tags_strip_replace'), $pTag);
		}
		return $pTag;
	}

	/* make tag data is safe to store
	 */
	function verifyTagsMap( &$pParamHash ) {
		global $gBitUser, $gBitSystem;

		$pParamHash['map_store'] = array();

		//this is to set the time we add content to a tag.
		$timeStamp = $gBitSystem->getUTCTime();

		//need to break up this string
		$tagMixed = isset($pParamHash['tags']) ? $pParamHash['tags'] : NULL;
		if( !empty( $tagMixed )){
			if (!is_array( $tagMixed ) && !is_numeric( $tagMixed ) ){
				$tagIds = explode( ",", $tagMixed );
			}else if ( is_array( $tagMixed ) ) {
				$tagIds = $tagMixed;
			}else if ( is_numeric( $tagMixed ) ) {
				$tagIds = array( $tagMixed );
			}

			foreach( $tagIds as $value ) {
				$value = LibertyTag::sanitizeTag($value);
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
		$this->mDb->StartTrans();
		$query = "DELETE FROM `".BIT_DB_PREFIX."tags_content_map` WHERE `tag_id` = ?";

		if ( $result = $this->mDb->query( $query, array( $tag_id ) ) ){
			// remove all references to tag in tags_content_map
			$query = "DELETE FROM `".BIT_DB_PREFIX."tags` WHERE `tag_id` = ?";
			if ( $result = $this->mDb->query( $query, array( $tag_id ) ) ) {
				$ret = TRUE;
			}else{
				//some rollback feature would be nice here
			}
		}
		$this->mDb->CompleteTrans();
		return $ret;
	}

	/**
	* This function removes all references to contentid from tags_content_map
	**/
	function expungeContentFromTagMap(){
		$ret = FALSE;
		if( $this->isValid() ) {
			$this->mDb->StartTrans();
			$query = "DELETE FROM `".BIT_DB_PREFIX."tags_content_map` WHERE `content_id` = ?";
			$result = $this->mDb->query( $query, array( $this->mContentId ) );
			$this->mDb->CompleteTrans();
		}
		return $ret;
	}

	/**
	* This function removes all references to contentid from tags_content_map
	**/
	function expungeMyContentFromTagMap(){
		global $gBitUser;
		$ret = FALSE;
		if( $this->isValid() ) {
			$this->mDb->StartTrans();
			$query = "DELETE FROM `".BIT_DB_PREFIX."tags_content_map` WHERE `content_id` = ? AND tagger_id = ?";
			$result = $this->mDb->query( $query, array( $this->mContentId, $gBitUser->mUserId  ) );
			$this->mDb->CompleteTrans();
		}
		return $ret;
	}

	/**
	 * The function removes one or more tag from a piece of content
	 */
	function expungeTags($pContentId, $pTagIdArray) {
		if (LibertyContent::verifyId($pContentId)) {
			$this->mDb->StartTrans();
			$query = "DELETE FROM `".BIT_DB_PREFIX."tags_content_map` WHERE `content_id` = ? AND tag_id IN (".implode( ',',array_fill( 0,count( $pTagIdArray ),'?' ) )." )";
			$bind[] = $pContentId;
			$bind = array_merge($bind, $pTagIdArray);
			$result = $this->mDb->query( $query, $bind );
			$this->mDb->CompleteTrans();
		}
	}

	/**
	* This function gets a list of tags
	**/
	function getList( &$pParamHash ) {
		global $gBitUser, $gBitSystem;

		$selectSql = ''; $joinSql = ''; $whereSql = '';
		$bindVars = array();

		$sort_mode_prefix = 'tg';

		$sortHash = array(
			'content_id_desc',
			'content_id_asc',
			'modifier_user_desc',
			'modifier_user_asc',
			'modifier_real_name_desc',
			'modifier_real_name_asc',
			'creator_user_desc',
			'creator_user_asc',
			'creator_real_name_desc',
			'creator_real_name_asc',
			'title_asc',
			'title_desc',
			'content_type_guid_asc',
			'content_type_guid_desc',
			'ip_asc',
			'ip_desc',
			'last_modified_asc',
			'last_modified_desc',
			'created_asc',
			'created_desc',
		);

		if( empty( $pParamHash['sort_mode'] ) || in_array( $pParamHash['sort_mode'], $sortHash ) ) {
			$pParamHash['sort_mode'] = 'tag_asc';
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

		$result = $this->mDb->query($query,$bindVars, ( !empty($pParamHash['max_records']) ? $pParamHash['max_records'] : NULL ));
		$cant = $this->mDb->getOne($query_cant,$bindVars);
		$ret = array();

		while ($res = $result->fetchRow()) {
			$res['popcant'] = $this->getPopCount($res['tag_id']);
			$ret[] = $res;
		}

		//get keys for doing sorts
		foreach ($ret as $key => $row) {
		   $popcant[$key]  = $row['popcant'];
		   $orderedcant[$key]  = $row['popcant'];
		}

		//this part creates the tag weight in a scale of 1-10
			//get highest count and get lowest count
		if (!empty($orderedcant)) {
			sort($orderedcant);

			$lowcant = $orderedcant[0];
			$highcant = $orderedcant[ (count($orderedcant) - 1) ];

			//hack to prevent us from dividing by zero - this whole weighting thing could use a slightly better formula
			if ($highcant == $lowcant){$lowcant -= 1;}

			//rescore
			//1.  High-low = x
			$cantoffset = $highcant - $lowcant;

			//2.  ratio 10/x
			if ($cantoffset > 9){
				$tagscale = 9/$cantoffset;
			}else{
				//@todo make this more sophisticated if the spread is not big enough
				$tagscale = 9/$cantoffset;
			}
			//3.  (n - low+1)*ratio  (n is # to be scaled)
			foreach ($ret as $key => $row) {
				$ret[$key]['tagscale']  = round((($row['popcant'] - $lowcant) * $tagscale) + 1, 0);
			}
		}

		//if the user has asked to sort the tags by use we sort the array before returning it
		if ( (isset($pParamHash['sort']) && $pParamHash['sort']=='mostpopular') ) {
			array_multisort($popcant, SORT_DESC, $ret);
		}
		
		//trim to max popular count if a limit is asked for
		if ( isset($pParamHash["max_popular"]) && is_numeric($pParamHash["max_popular"])){
			$max_popular = $ret;
			array_multisort($popcant, SORT_DESC, $max_popular);
	 		$max_popular = array_slice($max_popular, 0, $pParamHash["max_popular"]);
	 		// preserve the sort requested by matching to the original list
			$sorted_popular = array();
			foreach ( $ret as $retkey => $retrow){
				foreach ( $max_popular as $key => $row){
					if ( $row['tag_id'] ==  $retrow['tag_id'] ){
						$sorted_popular[] = $retrow;
						break;
					}
				}
			}
			$ret = $sorted_popular;
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
		return $cant;
	}

	/**
	* This function gets all content by matching to any tag passed in a group of tags, eliminates dupe records
	**/
	function getContentList(&$pParamHash){
		global $gBitSystem, $gBitSmarty;
		$_REQUEST['output'] = 'raw';
		include_once( LIBERTY_PKG_PATH.'list_content.php' );
		if ( isset($pParamHash['matchtags']) && $pParamHash['matchtags'] == 'all'){
			//need some sort of matching function
		}else{
			//match on any tags
			$distinctdata = $this->array_distinct( $contentList["data"], 'content_id' );
			$distinctdata = array_merge($distinctdata);
		}
		$gBitSmarty->assign_by_ref('contentList', $distinctdata);
	}


	/**
	* Used by getContentList to strip out duplicate records in a list
	* Lifted from http://us3.php.net/manual/en/function.array-unique.php#57006
	*
	* @param $array - nothing to say
	* @param $group_keys - columns which have to be grouped - can be STRING or ARRAY (STRING, STRING[, ...])
	* @param $sum_keys - columns which have to be summed - can be STRING or ARRAY (STRING, STRING[, ...])
	* @param $count_key - must be STRING - count the grouped keys
	*/
	function array_distinct ($array, $group_keys, $sum_keys = NULL, $count_key = NULL){
	  if (!is_array ($group_keys)) $group_keys = array ($group_keys);
	  if (!is_array ($sum_keys)) $sum_keys = array ($sum_keys);

	  $existing_sub_keys = array ();
	  $output = array ();

	  foreach ($array as $key => $sub_array){
	   $puffer = NULL;
	   #group keys
	   foreach ($group_keys as $group_key){
		 $puffer .= $sub_array[$group_key];
	   }
	   $puffer = serialize ($puffer);
	   if (!in_array ($puffer, $existing_sub_keys)){
		 $existing_sub_keys[$key] = $puffer;
		 $output[$key] = $sub_array;
	   }
	   else{
		 $puffer = array_search ($puffer, $existing_sub_keys);
		 #sum keys
		 foreach ($sum_keys as $sum_key){
		   if (is_string ($sum_key)) $output[$puffer][$sum_key] += $sub_array[$sum_key];
		 }
		 #count grouped keys
		 if (!array_key_exists ($count_key, $output[$puffer])) $output[$puffer][$count_key] = 1;
		 if (is_string ($count_key)) $output[$puffer][$count_key]++;
	   }
	  }
	  return $output;
	}

}

/********* SERVICE FUNCTIONS *********/
function tags_content_display( &$pObject ) {
	global $gBitSystem, $gBitSmarty, $gBitUser;
	if ( $gBitSystem->isPackageActive( 'tags' ) ) {
		if( $gBitUser->hasPermission( 'p_tags_view' ) ) {
			$tag = new LibertyTag( $pObject->mContentId );
			if( $tag->load() ) {
				$gBitSmarty->assign( 'tagData', !empty( $tag->mInfo['tags'] ) ? $tag->mInfo['tags'] : NULL );
			}
		}
	}
}

/**
 * filter the search with pigeonholes
 * @param $pParamHash['tags']['filter'] - a tag or an array of tags
 **/
function tags_content_list_sql( &$pObject, $pParamHash = NULL ) {
	global $gBitSystem;
	$ret = array();

	if (isset($pParamHash['tags'])){
		$ret['select_sql'] = ", tgc.`tag_id`, tgc.`tagger_id`, tgc.`tagged_on`";
		$ret['join_sql'] = " INNER JOIN `".BIT_DB_PREFIX."tags_content_map` tgc ON ( lc.`content_id`=tgc.`content_id` )
							 INNER JOIN `".BIT_DB_PREFIX."tags` tg ON ( tg.`tag_id`=tgc.`tag_id` )";

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

		$ret['where_sql'] = ' AND tg.`tag` IN ('.implode( ',', array_fill(0, count( $tagIds ), '?' ) ).')';

		$ret['bind_vars'] = $tagIds;
	}

	return $ret;
}

function tags_content_edit( $pObject=NULL ) {
	global $gBitSystem, $gBitSmarty, $gBitUser;
	if ( $gBitSystem->isPackageActive( 'tags' ) ) {
		$tag = new LibertyTag( $pObject->mContentId );
		if( $gBitUser->hasPermission( 'p_tags_create' ) || $gBitUser->hasPermission( 'p_tag_edit' )) {
			if( $tag->load() ) {
				if ($gBitUser->hasPermission( 'p_tags_edit' )) {
					$tags = array();
					foreach ($tag->mInfo['tags'] as $t) {

						if ($t['tagger_id'] == $gBitUser->mUserId) {
							$tags[] = $t['tag'];
						}
					}

					$gBitSmarty->assign( 'tagList', !empty( $tags ) ? implode(", ", $tags) : NULL );
				}
				$gBitSmarty->assign( 'tagData', !empty( $tag->mInfo['tags'] ) ? $tag->mInfo['tags'] : NULL );
			}
		}
	}
}

/**
 * @param includeds a string or array of 'tags' and contentid for association.
 **/
function tags_content_store( &$pObject, &$pParamHash ) {
	global $gBitUser, $gBitSystem;
	if( $gBitUser->hasPermission( 'p_tags_create' ) ) {
		$errors = NULL;
		// If a content access system is active, let's call it
		if( $gBitSystem->isPackageActive( 'tags' ) ) {
			$tag = new LibertyTag( $pObject->mContentId );
			if ( $gBitUser->hasPermission('p_tags_edit') ) {
				$tag->expungeMyContentFromTagMap();
			}
			if ( !$tag->storeTags( $pParamHash ) ) {
				$errors=$tag->mErrors;
			}
		}
		return( $errors );
	}
}

function tags_content_preview( &$pObject) {
	global $gBitUser, $gBitSystem, $gBitSmarty;
	if( $gBitUser->hasPermission( 'p_tags_create' ) ) {
		if ( $gBitSystem->isPackageActive( 'tags' ) ) {
			if (isset($_REQUEST['tags'])) {
			  //$pObject->mInfo['tags'] = $_REQUEST['tags'];
				$gBitSmarty->assign('tagList', $_REQUEST['tags']);
			}
		}
	}
}

function tags_content_expunge( &$pObject ) {
	$tag = new LibertyTag( $pObject->mContentId );
	$tag->expungeContentFromTagMap();
}
?>
