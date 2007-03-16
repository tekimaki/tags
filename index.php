<?php
require_once( "../bit_setup_inc.php" );
require_once( TAGS_PKG_PATH."LibertyTag.php" );

$gBitSystem->verifyPackage( 'tags' );

$tag = new LibertyTag();

$listHash = $_REQUEST;
$tagHash = $_REQUEST;

if( isset($_REQUEST['tags']) ){
	$listData = $tag->getContentList( $listHash );
	$tagData = $tag->getList( $tagHash );
	$gBitSmarty->assign( 'tagData', $tagData["data"] );
	$gBitSmarty->assign( 'tagsReq', $_REQUEST['tags'] );
	$gBitSystem->display( 'bitpackage:tags/list_content.tpl', tra( 'Tagged Content' ) );
}else{
	$listData = $tag->getList( $listHash );
	$gBitSmarty->assign( 'tagData', $listData["data"] );
	$gBitSystem->display( 'bitpackage:tags/list_tags.tpl', tra( 'Tags' ) );
}
?>