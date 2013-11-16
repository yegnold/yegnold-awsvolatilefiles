<?php

require_once 'vendor/autoload.php';

use Aws\Common\Aws as Aws;

$aws = Aws::factory('config/aws.config.php');

use Yegnold\AwsVolatileFileDownloadMirror;
$volatile_link_generator = new AwsVolatileFileDownloadMirror($aws, 'yegnold-example', 'http://dz9ncp1xthllc.cloudfront.net');
// Give the user 20 seconds to start their download. This should be more than enough!
$volatile_link_generator->setSecondsValid(20);
$volatile_link_generator->setIPV4Restriction('86.136.140.199');
// The remote file path, relative to the bucket root.
$volatile_link_generator->setRemoteResourceFilepath('records.backup.zip');

try {
	$volatile_link = $volatile_link_generator->createSignedUrl();
	echo $volatile_link;
} catch(Yegnold\UnableToMirrorResourceException $e) {
	echo $e->getMessage();
}