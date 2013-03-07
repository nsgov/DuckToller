<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright Province of Nova Scotia, 2013
 */

/**
 * TweetCache caches tweets from a twitter feed.
 *
 * When a cache needs updating, TweetCache requests from twitter any tweets
 * since the last time it checked.  The JSON repsonse is converted to an Atom
 * entry with the tweet data also marked up as XHTML in the Atom content tag.
 * The cache is saved as an Atom feed.
 */
class TweetCache extends Cachable {
	protected $atom, $entries, $config, $feedmode, $params;
	protected static $modes = array(
		 //           mode       API endpoint               primary param  valid param
		'@' => array('user',    '/statuses/user_timeline', 'screen_name', '\w{1,15}'),
		'#' => array('hashtag', '/search/tweets',          'q',           '[A-Za-z]\w{0,30}'),
		'*' => array('fav',     '/favorites/list',         'screen_name', '\w{1,15}'),
		'?' => array('search',  '/search/tweets',          'q',           '[[:print:]]{1,999}')
	);
	public static $XMLNS = array(
		'atom'    => 'http://www.w3.org/2005/Atom',
		'twitter' => 'http://api.twitter.com',
		'xhtml'   => 'http://www.w3.org/1999/xhtml'
	);

	function __construct($toller, $keys, $feedstring) {
		$this->config = $toller->config['tweetcache'];
		$this->keys = $keys;
		$feedchar = $feedstring{0};
		$feedparam = substr($feedstring, 1);
		if (!isset(self::$modes[$feedchar]))
			throw new Exception("Invalid feed mode character ($feedchar) for tweet request");
		$this->feedmode = self::$modes[$feedchar];
		if (!preg_match('/^'.$this->feedmode[3].'$/', $feedparam))
			throw new Exception('Invalid ' . $this->feedmode[2] . ' value for tweet request');
		$basename = preg_match('/^\w{1,31}$/', $feedparam) ? strtolower($feedparam) : md5($feedparam);
		$cachefile = 'feed/' . $this->feedmode[0] . "-$basename.atom";
		parent::__construct($toller, $cachefile);
		$this->max_age = 3600;
		$this->loglabel = "tweetcache[$feedstring]";
		if ($feedchar=='#')
			$feedparam = '#'.$feedparam;
		$this->params = array($this->feedmode[2] => $feedparam);
		$this->atom = new DOMDocument();
	}

	function mimetype() {
		return "application/atom+xml";
	}

	protected function fetch($cache) {
		require_once(DUCKTOLLER_PATH.'lib/twitteroauth/twitteroauth.php');
		$this->loadFromCache($cache);
		$this->extractEntries();
		$since_id = $this->getMaxId();
		if ($since_id)
			$this->params['since_id'] = $since_id;
		$this->log('Fetching tweets from twitter' . ($since_id?" (since $since_id)":''));
		$start = time();
		$toa = new TwitterOAuth($this->keys['CONSUMER_KEY'],
		                        $this->keys['CONSUMER_SECRET'],
		                        $this->keys['OAUTH_TOKEN'],
		                        $this->keys['OAUTH_TOKEN_SECRET']);
		$toa->host = 'https://api.twitter.com/1.1';
		$toa->connecttimeout = $this->config['timeout'];
		$tweets = $toa->get($this->feedmode[1], $this->params);
		$hc = $toa->http_code;
		#echo "<pre>"; print_r($toa->http_info); echo "\n"; print_r($tweets); echo "\n<pre>\n";
		if ($hc != 200)
			throw new Exception('Fail Whale! HTTP '.($hc||'timeout').' (after '.(time()-$start).'s)', $hc);
		if (isset($tweets->statuses))
			$tweets = $tweets->statuses;
		$n = count($tweets);
		$this->log('Received '.$n.' new tweet'.($n==1?'':'s'));
		if ($n > 0)
			$this->content = $this->generateFeed($tweets);
	}

	protected function loadFromCache($cache) {
		if (!$this->content) {
			parent::loadFromCache($cache);
			if ($this->content)
				$this->atom->loadXML($this->content);
			if (!$this->atom->documentElement)
				$this->atom->loadXML('<?xml version="1.0" encoding="utf-8"?>'.
				                     '<feed xmlns="'.self::$XMLNS['atom'].'" xmlns:twitter="'.self::$XMLNS['twitter'].'"/>');
		}
		else $this->log('Using cached tweets');
	}

	protected function extractEntries() {
		$this->entries = array();
		if (!$this->atom)
			return;
		$feed = $this->atom->documentElement;
		$tags = $feed->getElementsByTagNameNS(self::$XMLNS['atom'], 'entry');
		$this->log('Found ' . $tags->length . ' tweets in cache');
		for ($i = $tags->length; $i--;)
			$this->entries[$i] = $feed->removeChild($tags->item($i));
	}

	protected function getMaxId() {
		$max = 0;
		if (count($this->entries) > 0) {
			$tags = $this->entries[0]->getElementsByTagNameNS(self::$XMLNS['atom'], 'id');
			if ($tags && $tags->length) {
				$x = explode('/', $tags->item(0)->textContent);
				$max = trim($x[count($x)-1]);
			}
		}
		return $max;
	}

	protected function generateFeed($tweets) {
		$this->log('Generating Atom Feed');
		$id = htmlspecialchars('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
		switch ($this->feedmode[0]) {
		case 'user': $title = $tweets[0]->user->name . ' @' . $tweets[0]->user->screen_name . ' Tweets'; break;
		case 'hashtag': $title = '#' . $this->params['q']; break;
		default: $title = 'Tweets';
		}
		$title = htmlspecialchars($title);
		$feed = $this->atom->documentElement;
		while ($feed->lastChild)
			$feed->removeChild($feed->lastChild);
		$feed->appendChild($this->atom->createTextNode("\n"));
		$this->appendAtomTag($feed, 'id', null, $id);
		$this->appendAtomTag($feed, 'title', null, $title);
		$this->appendAtomTag($feed, 'link', array('rel'=>'self', 'type'=>'text/html', 'href'=>$id));
		$this->appendAtomTag($feed, 'generator', array('uri'=>'http://github.com/nsgov/ducktoller',
		                                               'version'=> DuckToller::$version),
		                     'DuckToller/'.DuckToller::$version);
		$this->appendAtomTag($feed, 'updated', null, date(DATE_W3C));
		foreach ($tweets as $t)
			$this->generateEntry($t);
		foreach ($this->entries as $e)
			$feed->appendChild($e);
		return $this->atom->saveXML($feed);
	}

	protected function generateEntry($tweet) {
		$entry = $this->appendAtomTag($this->atom->documentElement, 'entry');
		$retweeted_by = '';
		$title = $tweet->user->screen_name . ': ' . $tweet->text;
		$updated = $published = new DateTime($tweet->created_at);
		if (isset($tweet->retweeted_status)) {
			$retweeted_by = '<p class="retweetedby"><i class="tweet-icon"> </i> Retweeted by '.
			                '<a href="https://twitter.com/'.
							htmlspecialchars($tweet->user->screen_name).'">'.
			                $tweet->user->name.'</a></p>';
			$tweet = $tweet->retweeted_status;
			$published = new DateTime($tweet->created_at);
		}
		$id = $tweet->id_str;
		$username = $tweet->user->screen_name;
		$author_url = 'https://twitter.com/'.$username;
		$tweet_url = "$author_url/status/$id";
		$this->appendAtomTag($entry, 'id', null, $tweet_url);
		$this->appendAtomTag($entry, 'link', array('rel'=>'alternate', 'type'=>'text/html', 'href'=>$tweet_url));
		$this->appendAtomTag($entry, 'title', null, $title);
		$author = $this->appendAtomTag($entry, 'author');
		$this->appendAtomTag($author, 'name', null, $tweet->user->name);
		$this->appendAtomTag($author, 'uri', null, $author_url);
		$this->appendTwitterTag($author, 'screen_name', $username);
		$imgsrc = $tweet->user->profile_image_url;
		$this->appendTwitterTag($author, 'profile_image_url', $imgsrc);
		$this->appendAtomTag($entry, 'published', null, $published->format(DateTime::ATOM));
		$this->appendAtomTag($entry, 'updated', null, $updated->format(DateTime::ATOM));
		$this->appendAtomTag($entry, 'summary', array('type'=>'xhtml'), $tweet->text);
		$entity = array();
		$indices = array();
		foreach ($tweet->entities->hashtags as $hashtag) {
			$term = '#'.$hashtag->text;
			$this->appendAtomTag($entry, 'category', array(
				'term'   => $term,
				'scheme' => 'https://twitter.com/'
			));
			$indices[] = $i = $hashtag->indices[0];
			$indices[] = $hashtag->indices[1];
			$entity["i$i"] = $this->makeLink('https://twitter.com/search/%23'.$hashtag->text, $term);
		}
		foreach ($tweet->entities->urls as $url) {
			$indices[] = $i = $url->indices[0];
			$indices[] = $url->indices[1];
			$d_url = isset($url->display_url)  ? $url->display_url  : $url->url;
			$x_url = isset($url->expanded_url) ? $url->expanded_url : null;
			$entity["i$i"] = $this->makeLink($url->url, $d_url, $x_url);
		}
		foreach ($tweet->entities->user_mentions as $user) {
			$indices[] = $i = $user->indices[0];
			$indices[] = $user->indices[1];
			$entity["i$i"] = $this->makeLink('https://twitter.com/'.$user->screen_name, $user->screen_name, $user->name);
		}
		$text = $this->linkEntities($tweet->text, $indices, $entity);
		$intent = 'https://twitter.com/intent/';
		$html =
			'<div class="tweet">'.
			'<a href="'.$author_url.'" class="tweeter">'.
			'<img src="'.$imgsrc.'" alt="" class="tweeter-avatar" />'.
			'<span class="tweeter-name">'.htmlspecialchars($tweet->user->name).'</span> '.
			'<span class="tweeter-screenname">@'.htmlspecialchars($tweet->user->screen_name).'</span>'.
			'</a> '.
			'<a href="'.$tweet_url.'" class="tweet-time" title="'.$tweet->created_at.'">'.
			'<time datetime="'.$updated->format(DateTime::W3C).'">'.$updated->format('M d').'</time>'.
			'</a> '.
			'<blockquote class="tweet-text" cite="'.$tweet_url.'">'.$text.'</blockquote> '.
			$retweeted_by.
			'<div class="tweet-actions">'.
			'<a href="'.$intent.'tweet?in_reply_to='.$id.'" class="tweet-action tweet-reply"><i class="tweet-icon"> </i> Reply</a>'.
			'<a href="'.$intent.'retweet?tweet_id=' .$id.'" class="tweet-action tweet-retweet"><i class="tweet-icon"> </i> Retweet</a>'.
			'<a href="'.$intent.'favorite?tweet_id='.$id.'" class="tweet-action tweet-fav"><i class="tweet-icon"> </i> Favourite</a> '.
			'</div>'.
			'</div>';
		$content = $this->appendAtomTag($entry, 'content', array('type'=>'html'), $html);
		$this->appendTwitterTag($entry, 'source', $tweet->source);
		return $entry;
	}

	protected function linkEntities($text, $indices, $entities) {
		if (!in_array(0, $indices))
			$indices[] = 0;
		sort($indices, SORT_NUMERIC);
		$last = count($indices) - 1;
		$len = iconv_strlen($text, 'UTF-8');
		if ($indices[$last] != $len) {
			$indices[] = $len;
			$last++;
		}
		$html = array();
		for ($i = 0; $i < $last; $i++) {
			$idx = $indices[$i];
			$key = "i$idx";
			if (isset($entities[$key]))
				$html[] = $entities[$key];
			else
				$html[] = iconv_substr($text, $idx, $indices[$i+1] - $idx, 'UTF-8');
		}
		return implode($html);
	}

	private function xmlTag($doc, $ns, $tagName, $attr=null, $text=null) {
		$tag = $doc->createElementNS(self::$XMLNS[$ns], $tagName);
		if ($attr)
			foreach ($attr as $name => $val)
				$tag->setAttribute($name, $val);
		if ($text)
			$tag->appendChild($doc->createTextNode($text));
		return $tag;
	}
	private function appendAtomTag($parent, $tagName, $attr=null, $text=null) {
		$t = $this->xmlTag($parent->ownerDocument, 'atom', $tagName, $attr, $text);
		return $this->appendTag($parent, $t, "\t");
	}
	private function appendHtmlTag($parent, $tagName, $attr=null, $text=null) {
		$t = $this->xmlTag($parent->ownerDocument, 'xhtml', $tagName, $attr, $text);
		return $this->appendTag($parent, $t, "\t\t");
	}
	private function appendTwitterTag($parent, $tagName, $text) {
		$t = $this->xmlTag($parent->ownerDocument, 'twitter', "twitter:$tagName", null, $text);
		return $this->appendTag($parent, $t, "\t");
	}
	private function appendTag($parent, $tag, $indent) {
		$parent->appendChild($parent->ownerDocument->createTextNode($indent));
		$parent->appendChild($tag);
		$parent->appendChild($parent->ownerDocument->createTextNode("\n"));
		return $tag;
	}
	private function makeLink($href, $text, $title=null, $className=null) {
		return '<a href="'.htmlspecialchars($href).'"'.
			($title?' title="'.htmlspecialchars($title).'"':'').
			($className?' class="'.$className.'"':'').
			' rel="nofollow">' . htmlspecialchars($text) . '</a>';
	}
};
