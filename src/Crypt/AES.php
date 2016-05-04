<?php
namespace mywin\Crypt;
use phpseclib\Crypt\AES;
/**
 * Pure-PHP implementation of AES.
 *
 * @package AES
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class AES {
	public static $key = "1234567890123456";

	public static function encrypt($data)
	{
		$aes = new AES(AES::MODE_ECB);
		$aes->setKey(self::$key);
		$data = $aes->encrypt($data);
		$data = bin2hex($data);
		return $data;
	}

	public static function decrypt($data)
	{
		$aes = new AES(AES::MODE_ECB);
		$aes->setKey(self::$key);
		$data = pack("H*", $data);
		$data = $aes->decrypt($data);
		return $data;
	}
}
