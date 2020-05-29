CREATE TABLE IF NOT EXISTS `#__ext_urls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ext` varchar(50) NOT NULL DEFAULT '',
  `original` varchar(50) NOT NULL DEFAULT '0',
  `fake` varchar(50) NOT NULL DEFAULT '0',
  `alias` varchar(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
)  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;