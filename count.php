<?php
class Count
{
	public static $number = 0;
	
	public static function GetCount()
	{
		return self::$number;
	}
	
	public static function Initialize()
	{
		return self::$number = 0;
	}
	
	public static function Addition()
	{
		return ++self::$number;
	}
	
	public static function Subtraction()
	{
		return --self::$number;
	}
	
	public static function Multiplication($num = 1)
	{
		return self::$number *= $num;
	}
	
	public static function Division($num = 1)
	{
		if($num)
			return self::$number /= $num;
		else
			return self::$number;
	}
}
//echo count::Addition();
//echo count::Addition();
//echo count::Multiplication(2);
//echo count::Division(0);