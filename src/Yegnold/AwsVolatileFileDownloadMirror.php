<?php
/**
 * "Signing CloudFront URLs for Private Distribution - http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-cloudfront.html#signing-cloudfront-urls-for-private-distributions
 * http://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/PrivateContent.html
 */

namespace Yegnold;

class AwsVolatileFileDownloadMirror  {

	/**
	 * @var Aws an instance of Aws\Common\Aws
	 */
	protected $aws;

	/**
	 * @var int The number of seconds the volatile link will be valid for.
	 */
	protected $seconds_valid = 120;

	/**
	 * @var string IP address restriction. An IPV4 Ip address restriction
	 */
	protected $ipv4_restriction = '';

	/**
	 * @var string the s3 bucket name e.g. yegnold-example
	 */
	protected $s3_bucket_name;

	/**
	 * @var string the cloudfront host url e.g. http://xyzabc123c.cloudfront.net
	 */

	protected $cloudfront_host_url;

	/**
	 * @var string $remote_resource_filepath The remote file path relative to the $cloudfront_host_url or bucket root.
	 */
	protected $remote_resource_filepath = '';


	public function __construct(\Aws\Common\Aws $aws, $s3_bucket_name, $cloudfront_host_url)
	{
		$this->aws = $aws;
		$this->s3_bucket_name = $s3_bucket_name;
		$this->cloudfront_host_url = $cloudfront_host_url;
	}

	/**
	 * @param int $seconds_valid The number of seconds the signed URL will be valid for
	 */
	public function setSecondsValid($seconds_valid)
	{
		if(!is_int($seconds_valid) || $seconds_valid < 1) {
			throw new Exception('$seconds_valid parameter passed to setSecondsValid() expected a positive integer.');
		}
		$this->seconds_valid = $seconds_valid;
	}

	/**
	 * @param string $ipv4_address a valid IPV4 address, that will be the only IP address allowed to download this file
	 */
	public function setIPV4Restriction($ipv4_address)
	{
		if(!filter_var($ipv4_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			throw new Exception('Invalid IPV4 address received to setIPV4Restriction() method.');
		}
		$this->ipv4_restriction = $ipv4_address;
	}

	public function setRemoteResourceFilepath($remote_resource_filepath) {
		$this->remote_resource_filepath = $remote_resource_filepath;
	}

	public function createSignedUrl()
	{

		$s3_client = $this->aws->get('S3');
		$s3_client->registerStreamWrapper();

		if(!file_exists('s3://'.$this->s3_bucket_name.'/'.$this->remote_resource_filepath)) {
			throw new UnableToMirrorResourceException('The resource '.$this->remote_resource_filepath.' does not exist in the S3 bucket');
		}

		$cloudfront_client = $this->aws->get('CloudFront');

		$ip_address_portion = '';
		
		if($this->ipv4_restriction != '') {
			$ip_address_portion = <<<IPV4_PORTION
"IpAddress": {"AWS:SourceIp":"{$this->ipv4_restriction}/32"},
IPV4_PORTION;
		}

		$expires = time() + $this->seconds_valid;

		$custom_policy = <<<POLICY
{
		    "Statement": [
		    	{
		            "Resource": "{$this->cloudfront_host_url}/{$this->remote_resource_filepath}",
		            "Condition": {
		                {$ip_address_portion}
		                "DateLessThan": {"AWS:EpochTime":{$expires}}
		            }
		    }
		    ]
}
POLICY;

		$signed_url_custom_policy = $cloudfront_client->getSignedUrl(array(
		    'url'    => $this->cloudfront_host_url . '/' . $this->remote_resource_filepath,
		    'policy' => $custom_policy,
		));

		return $signed_url_custom_policy;
	}

}

use \Exception as Exception;
class UnableToMirrorResourceException extends Exception { }

/** 
* Example use:
* 
* use Aws\Common\Aws as Aws;
* $aws = Aws::factory('config/aws.config.php');
* 
* use Yegnold\AwsVolatileFileDownloadMirror;
* $volatile_link_generator = new AwsVolatileFileDownloadMirror($aws, 'yegnold-example', 'http://dz9ncp1xthllc.cloudfront.net');
* // Give the user 20 seconds to start their download. This should be more than enough!
* $volatile_link_generator->setSecondsValid(20);
* $volatile_link_generator->setIPV4Restriction('86.136.140.199');
* // The remote file path, relative to the bucket root.
* $volatile_link_generator->setRemoteResourceFilepath('records.backup.zip');
* 
* try {
* 	$volatile_link = $volatile_link_generator->createSignedUrl();
* 	echo $volatile_link;
* } catch(Yegnold\UnableToMirrorResourceException $e) {
* 	 echo $e->getMessage();
* }
*/