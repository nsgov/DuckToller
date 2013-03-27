<?xml version="1.0" encoding="utf-8"?>
<xsl:transform version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:twitter="http://api.twitter.com"
	xmlns:xhtml="http://www.w3.org/1999/xhtml"
	xmlns="http://www.w3.org/1999/xhtml"
	exclude-result-prefixes="atom twitter xhtml">
<xsl:output method="html" omit-xml-declaration="yes" indent="yes"/>

<xsl:param name="max" select="1"/>

<xsl:template match="atom:feed">
	<xsl:variable name="twURL" select="atom:link[not(@rel) or @rel='alternate'][@type='text/html']"/>
	<div class="twitterfeed">
		<div class="twitterfeed-header">
			<strong><xsl:choose>
			<xsl:when test="$twURL"><a href="{$twURL}" class="twitterfeed-title"><xsl:value-of select="atom:title"/></a></xsl:when>
			<xsl:otherwise><span class="twitterfeed-title"><xsl:value-of select="atom:title"/></span></xsl:otherwise>
			</xsl:choose></strong>
		</div>
		<xsl:apply-templates select="atom:entry[position() - 1 &lt; $max]"/>
	</div>
</xsl:template>

<xsl:template match="atom:entry">
	<xsl:variable name="id" select="substring-after(atom:id, '/status/')"/>
	<xsl:variable name="intent" select="'https://twitter.com/intent/'"/>
	<div class="tweet">
		<a href="{atom:id}" class="tweet-time" title="">
			<time datetime="{atom:updated}"></time>
		</a>
		<xsl:apply-templates select="atom:author"/>
		<blockquote class="tweet-text" cite="{atom:id}"><xsl:apply-templates select="atom:summary"/></blockquote>
		$retweeted_by
		<div class="tweet-actions">
		<a href="{$intent}tweet?in_reply_to={$id}" class="tweet-action tweet-reply"><i class="tweet-icon"> </i>Reply</a>
		<a href="{$intent}retweet?tweet_id={$id}" class="tweet-action tweet-retweet"><i class="tweet-icon"> </i>Retweet</a>
		<a href="{$intent}favorite?tweet_id={$id}" class="tweet-action tweet-fav"><i class="tweet-icon"> </i>Favourite</a>
		</div>
	</div>
</xsl:template>

<xsl:template match="atom:author">
	<a href="{atom:uri}" class="tweeter">
		<img src="{twitter:profile_image_url}" alt="" class="tweeter-avatar" />
		<span class="tweeter-name"><xsl:value-of select="atom:name"/></span>
		<span class="tweeter-screenname"><xsl:value-of select="twitter:screen_name"/></span>
	</a>
</xsl:template>

<xsl:template match="atom:summary[@type='xhtml']">
	<xsl:apply-templates select="xhtml:div/node()"/>
</xsl:template>

</xsl:transform>
