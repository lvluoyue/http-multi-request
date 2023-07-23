<?php
/**
 * Curl并发请求封装
 * @copyright 2023 Xiao Xin
 * @author: Xiao Xin <1569097443@qq.com>
 * @version 1.0 2023/2/21
 * @version 0.1 2023/1/6
 * 版本更新：
 * 1、优化批处理算法并规范部分变量
 * 2、修复请求超时的bug(由于网络、服务器等环境因素，请根据自身情况合理设置）
 * 3、增加最大并发设置(即一次最大请求url数量，合理设置可优化请求效率和请求超时)
 * 4、增加闭包回调，实现实时返回数据
 * 5、增加部分参数的可选数据(未经过具体测试，实际效果可能有些出入)
 * 本程序是课余时间写的，代码不是很规范，请见谅。。
 */
 
class Http
{
	private $mh;
	private $urls = [];
	private $data = [];//请求数据
	private $thread = 1;//最大并发数
	private $AfterMiddleware;//后置中间件
	private $BeforeMiddleware;//前置中间件
	private $options = [
		CURLOPT_TIMEOUT => 10,//设置超时时间
		CURLOPT_HEADER => true,//显示头文件信息
		CURLOPT_ENCODING => "gzip",//使用gzip解码
		CURLOPT_SSL_VERIFYPEER => false,//禁用ssl证书
		CURLOPT_SSL_VERIFYHOST => false,//禁用ssl加密
		CURLOPT_RETURNTRANSFER => 1,//结果不输出到浏览器
		CURLOPT_HTTPHEADER => [],//请求头
	];
	
	/**
	 * 实例化对象
	 *
	 * @access public
	 * @param string | array $urls 一个或多个url
	 * @return void
	 */
	public function __construct($urls)
	{
		$this->urls = (array)$urls;
		$this->data = [
			"getinfo" => [],
			"error" => null,
			"body" => null,
			"header" => null,
		];
	}
	
	/**
	 * 额外增加url队列
	 *
	 * @access public
	 * @param string | array $urls 一个或多个url
	 * @return object
	 */
	public function add_url($urls)
	{
		/*
		foreach((array)$urls as $v)
		{
			$this->urls[] = $v;
		}
		*/
		array_push($this->urls, ...(array)$urls);
		return $this;
	}
	
	/**
	 * 设置最大并发数
	 *
	 * @access public
	 * @param int $data [1,∞]
	 * @return object
	 */
	public function set_MaxThread(int $data)
	{
		$this->thread = $data;
		return $this;
	}
	
	/**
	 * 设置前置中间件(用于请求之前)
	 *
	 * @access public
	 * @param object $data 闭包函数
	 * @return object
	 */
	public function set_BeforeMiddleware($data)
	{
		$this->BeforeMiddleware = $data;
		return $this;
	}
	
	/**
	 * 设置后置中间件(用于请求之后)
	 *
	 * @access public
	 * @param object $data 闭包函数
	 * @return object
	 */
	public function set_AfterMiddleware($data)
	{
		$this->AfterMiddleware = $data;
		return $this;
	}
	
	/**
	 * 设置HTTP代理
	 *
	 * @access public
	 * @param string $data 代理服务器IP及端口号
	 * @return object
	 */
	public function set_proxy($data)
	{
		$this->options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
		$this->options[CURLOPT_PROXY] = $data;
		return $this;
	}
	
	/**
	 * 开启重定向
	 *
	 * @access public
	 * @param int $data 重定向最大次数，0为拒绝重定向，-1无限制，默认20
	 * @return object
	 */
	public function set_location($data = 20)
	{
		$this->options[CURLOPT_MAXREDIRS] = (int)$data;
		$this->options[CURLOPT_FOLLOWLOCATION] = (bool)$data;
		return $this;
	}
	
	/**
	 * 设置请求超时
	 *
	 * @access public
	 * @param float $data 0为不限时，最小可设置为1000毫秒，即1秒
	 * @return object
	 */
	public function set_timeout(float $data)
	{
		/*
		//回调
		$this->options[CURLOPT_HEADERFUNCTION] = function($response, $header) {
			echo $header;
			return strlen($header);
		};
		*/
		$this->options[CURLOPT_TIMEOUT_MS] = (int)($data * 1000);
		return $this;
	}
	
	/**
	 * 设置请求cookie
	 *
	 * @access public
	 * @param string | array $data cookie字符串格式或键值对
	 * @return object
	 */
	public function set_cookie($data)
	{
		switch(gettype($data))
		{
			case "string":
				break;
			case "array":
				$arr = [];
				foreach($data as $k => $v)
				{
					$arr[] = $k . '=' . $v;
				}
				$data = implode('; ', $arr);
				break;
			default:
			
			break;
		}
		$this->options[CURLOPT_COOKIE] = $data;
		return $this;
	}
	
	/**
	 * TCP优化，应该需要php7.4以上(效果待测试)
	 *
	 * @access public
	 * @param bool $data true | false
	 * @return object
	 */
	public function set_tcp_opt($data = 1)
	{
		$this->options[CURLOPT_PIPEWAIT] = (bool)$data;//如果为 true，则等待流水线/多路复用。	在 cURL 7.43.0 中添加。从 PHP 7.0.7 开始可用。
		$this->options[CURLOPT_TCP_NODELAY] = (bool)$data;//禁用 TCP 的 Nagle 算法，就是减少网络上的小包数量
		$this->options[CURLOPT_DNS_USE_GLOBAL_CACHE] = (bool)$data;//使用全局 DNS 缓存
		//$this->options[CURLOPT_TCP_FASTOPEN] = (bool)$data;//开启 TCP Fast Open
		return $this;
	}
	
	/**
	 * 设置请求header
	 *
	 * @access public
	 * @param string | array $data header字符串或键值数组
	 * @return object
	 */
	public function set_header($data)
	{
		switch(gettype($data))
		{
			case "string":
				foreach(explode(PHP_EOL, $data) as $v)
				{
					$tmp = explode(":", $v);
					if(count($tmp) >= 2)
						$this->options[CURLOPT_HTTPHEADER][$tmp[0]] = $v;
				}
				break;
			case "array":
				foreach($data as $k => $v)
				{
					if(is_string($k))
					{
						$this->options[CURLOPT_HTTPHEADER][$k] = $k . ': ' . $v;
					}else{
						$tmp = explode(":", $v);
						if(count($tmp) >= 2)
							$this->options[CURLOPT_HTTPHEADER][$tmp[0]] = $v;
					}
				}
				break;
			default:
			
			break;
		}
		return $this;
	}
	
	/**
	 * 设置请求来源
	 *
	 * @access public
	 * @param string $data 来源URL
	 * @return object
	 */
	public function set_referer(string $data)
	{
		$this->options[CURLOPT_REFERER] = $data;
		return $this;
	}
	
	/**
	 * 设置请求ua
	 *
	 * @access public
	 * @param string $data 来源URL
	 * @return object
	 */
	public function set_ua(string $data)
	{
		$this->options[CURLOPT_USERAGENT] = $data;
		return $this;
	}
	
	//使用post，并传入post参数
	public function post($data)
	{
		$this->options[CURLOPT_POST] = 1;
		$this->options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
		return $this;
	}
	
	//使用put，并传入put参数
	public function put($data)
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = "PUT";
		$this->options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
		return $this;
	}
	
	//使用delete，并传入delete参数
	public function delete($data)
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = "DELETE";
		$this->options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
		return $this;
	}
	
	//使用patch，并传入patch参数
	public function patch($data)
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = "PATCH";
		$this->options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
		return $this;
	}
	
	//使用options，并传入options参数
	public function options($data)
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = "OPTIONS";
		$this->options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
		return $this;
	}
	
	/**
	 * 获取响应体数据
	 *
	 * @access public
	 * @return string
	 */
	public function get_body()
	{
		if(!isset($this->mh))
		{
			$this->run();
		}
		return $this->data['body'];
	}
	
	/**
	 * 获取响应header
	 *
	 * @access public
	 * @return string
	 */
	public function get_header()
	{
		if(!isset($this->mh))
		{
			$this->run();
		}
		return $this->data['header'];
	}
	
	/**
	 * 获取重定向url
	 *
	 * @access public
	 * @return string
	 */
	public function get_location()
	{
		if(!isset($this->mh))
		{
			$this->run();
		}
		return $this->data['getinfo']["redirect_url"];
	}
	
	/**
	 * 获取curl请求信息
	 *
	 * @access public
	 * @return array
	 */
	public function get_info()
	{
		if(!isset($this->mh))
		{
			$this->run();
		}
		return $this->data['getinfo'];
	}
	
	/**
	 * 获取请求最后一次错误
	 *
	 * @access public
	 * @return string
	 */
	public function get_error()
	{
		if(!isset($this->mh))
		{
			$this->run();
		}
		return $this->data['error'];
	}
	
	/**
	 * 获取url队列数量
	 *
	 * @access public
	 * @return int
	 */
	public function count()
	{
		return count($this->urls);
	}
	
	/**
	 * 获取响应code
	 *
	 * @access public
	 * @return int
	 */
	public function get_HttpCode()
	{
		if(!isset($this->mh))
		{
			$this->run();
		}
		return $this->data['getinfo']["http_code"];
	}
	
	/**
	 * 获取响应的set-cookie
	 *
	 * @access public
	 * @return array
	 */
	public function get_HttpCookie()
	{
		if(!isset($this->mh))
		{
			$this->run();
		}
		$header = $this->get_header();
		preg_match_all("/set\-cookie:([^\r\n]*)/i", $header, $matches);
		$data = [];
		foreach($matches[1] as $v)
		{
			$cookies = explode(';', $v);
			foreach($cookies as $cv)
			{
				$ck = explode("=", $cv);
				switch(count($ck))
				{
					case 0:
						break;
					case 1:
						$data[$ck[0]] = false;
						break;
					default:
						$value = trim(array_pop($ck));
						while(count($ck))
						{
							$key = trim(array_pop($ck));
							//$tmp = strtolower($key);
							//if($tmp != "expires" && $tmp != "path" && $tmp != "domain")
								$data[$key] = $value;
						}
					break;
				}
			}
		}
		return $data;
	}
	
	/**
	 * 执行curl请求
	 *
	 * @access public
	 * @return void
	 */
	public function run()
	{
		!isset($this->mh) && $this->mh = curl_multi_init();
		$active = null;
		$this->listen_in($active);
		do {
			$mrc = curl_multi_exec($this->mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($this->mh,0.1) == -1) usleep(100); 
			do {
				if($active < $this->thread && $this->count() > 0) $this->listen_in($active);
				$mrc = curl_multi_exec($this->mh, $active);
				while ($done = curl_multi_info_read($this->mh)) {
					$this->data['error'] = curl_error($done['handle']);
					$this->data['getinfo'] = curl_getinfo($done['handle']);
					$data = curl_multi_getcontent($done['handle']);
					$headerSize = $this->data['getinfo']["header_size"];
					$this->data['header'] = substr($data, 0, $headerSize);
					$this->data['body'] = substr($data, $headerSize);
					curl_multi_remove_handle($this->mh, $done['handle']);
					curl_close($done['handle']);
					!empty($this->AfterMiddleware) && ($this->AfterMiddleware)($active);
				}
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
	}
	
	/**
	 * 监听批处理数量并创建新的句柄
	 *
	 * @access private
	 * @return void
	 */
	private function listen_in($active = 0)
	{
		$thread = $this->count() < $this->thread ? $thread = $this->count() : $this->thread;
		for($i = 0 ;$i < $thread - $active; $i++)
		{
			!empty($this->BeforeMiddleware) && ($this->BeforeMiddleware)($active);
			$ch = curl_init();
			$this->options[CURLOPT_URL] = array_shift($this->urls);
			curl_setopt_array($ch, $this->options);
			curl_multi_add_handle($this->mh, $ch);
			unset($this->options[CURLOPT_URL]);
		}
	}
	
	/**
	 * 关闭curl批处理句柄
	 *
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		isset($this->mh) && curl_multi_close($this->mh);
	}
}