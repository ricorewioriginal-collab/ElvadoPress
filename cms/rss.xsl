<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<xsl:output method="html" encoding="UTF-8"/>
<xsl:template match="/">
<html lang="de"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><xsl:value-of select="rss/channel/title"/></title>
<style>
body{margin:0;background:#070a1c;color:#e9ecff;font-family:Segoe UI,Arial,sans-serif}.wrap{max-width:900px;margin:auto;padding:34px 18px}.head{padding:22px;border:1px solid #252b58;border-radius:16px;background:#0f1538;margin-bottom:18px}h1{margin:0 0 8px;color:#fff}.sub{color:#aab3ca}.item{padding:18px;border:1px solid #252b58;border-radius:14px;background:#0b102d;margin:10px 0}.item a{color:#cba7ff;font-size:1.15rem;font-weight:800;text-decoration:none}.meta{font-size:.75rem;color:#7f89aa;margin:6px 0}.desc{color:#b7bfd5;line-height:1.6}.rss{display:inline-block;margin-top:10px;color:#22d3ee}
</style></head><body><div class="wrap"><div class="head"><h1><xsl:value-of select="rss/channel/title"/></h1><div class="sub"><xsl:value-of select="rss/channel/description"/></div><div class="rss">RSS 2.0 · Diese Ansicht bleibt vollständig feed-reader-kompatibel.</div></div>
<xsl:for-each select="rss/channel/item"><div class="item"><a><xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute><xsl:value-of select="title"/></a><div class="meta"><xsl:value-of select="pubDate"/> · <xsl:value-of select="category"/></div><div class="desc"><xsl:value-of select="description"/></div></div></xsl:for-each>
</div></body></html>
</xsl:template></xsl:stylesheet>