CREATE TABLE `dnsbl_reject_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `dnsbl` varchar(64) NOT NULL,
  `rejections` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`date`,`dnsbl`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
;
