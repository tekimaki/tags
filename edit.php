<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_tags/edit.php,v 1.4 2008/06/25 22:21:25 spiderr Exp $
 * @package tags
 * @subpackage functions
 * 
 * @copyright Copyright (c) 2004-2006, bitweaver.org
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
 */

/**
 * required setup
 */
require_once( "../bit_setup_inc.php" );
require_once( TAGS_PKG_PATH."LibertyTag.php" );

$gBitSystem->verifyPackage( 'tags' );

if ( !$gBitUser->hasPermission('p_tags_admin') ){
	$gBitSystem->fatalError( 'You do not have permission to edit Tags' );
}

$tag = new LibertyTag();

if ( $tag->loadTag ( $_REQUEST ) ){
	$gBitSmarty->assign( 'tagData', $tag->mInfo );	
}

if( !empty( $_REQUEST["save"] ) ) {
	if( $tag->storeOneTag( $_REQUEST ) ) {
		header ( "location: " . TAGS_PKG_URL. "index.php?tags=".$tag->mInfo['tag'] );
	}
}

$gBitSystem->display( 'bitpackage:tags/edit_tag.tpl', tra( "Edit Tag" ) , array( 'display_mode' => 'edit' ));
?>