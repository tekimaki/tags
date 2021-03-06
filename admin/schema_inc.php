<?php

global $gBitSystem;

$gBitSystem->registerPackageInfo( TAGS_PKG_NAME, array(
	'description' => "A simple Liberty Service that any package can use to tag its content with key words.",
	'license' => '<a href="http://www.gnu.org/licenses/licenses.html#LGPL">LGPL</a>',
) );

// Requirements
$gBitSystem->registerRequirements( TAGS_PKG_NAME, array(
    'liberty' => array( 'min' => '2.1.4' ),
));


// Install process
global $gBitInstaller;
if( is_object( $gBitInstaller ) ){

$tables = array(
  'tags' => "
    tag_id I4 PRIMARY,
    tag C(64) NOTNULL
  ",

  'tags_content_map' => "
    tag_id I4 NOTNULL,
    content_id I4 NOTNULL,
    tagger_id I4 NOTNULL,
    tagged_on I8 NOTNULL
    CONSTRAINT ', CONSTRAINT `tags_content_map_tag_ref` FOREIGN KEY (`tag_id`) REFERENCES `".BIT_DB_PREFIX."tags` ( `tag_id` )
                , CONSTRAINT `tags_content_map_content_ref` FOREIGN KEY (`content_id`) REFERENCES `".BIT_DB_PREFIX."liberty_content` ( `content_id` )
                , CONSTRAINT `tags_content_map_tagger_id_ref` FOREIGN KEY (`tagger_id`) REFERENCES `".BIT_DB_PREFIX."users_users` ( `user_id` )'
  "
);

foreach( array_keys( $tables ) AS $tableName ) {
	$gBitInstaller->registerSchemaTable( TAGS_PKG_NAME, $tableName, $tables[$tableName] );
}

$gBitInstaller->registerPreferences( TAGS_PKG_NAME, array(
	array( TAGS_PKG_NAME, 'tags_in_view', 'y' ),
		array( TAGS_PKG_NAME, 'tags_list_title', 'y' ),
		array( TAGS_PKG_NAME, 'tags_list_type', 'y' ),
		array( TAGS_PKG_NAME, 'tags_list_author', 'y' ),
		array( TAGS_PKG_NAME, 'tags_list_lastmodif', 'y' ),
) );

// ### Sequences
$sequences = array (
  'tags_tag_id_seq' => array( 'start' => 1 ),
);
$gBitInstaller->registerSchemaSequences( TAGS_PKG_NAME, $sequences );


// ### Default UserPermissions
$gBitInstaller->registerUserPermissions( TAGS_PKG_NAME, array(
	array( 'p_tags_admin', 'Can admin tags', 'admin', TAGS_PKG_NAME ),
	array( 'p_tags_create', 'Can create tags', 'registered', TAGS_PKG_NAME ),
	array( 'p_tags_view', 'Can view tags', 'basic', TAGS_PKG_NAME ),
	array( 'p_tags_moderate', 'Can edit tags', 'editors', TAGS_PKG_NAME ),
) );

}
