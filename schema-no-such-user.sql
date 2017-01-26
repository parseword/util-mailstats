CREATE TABLE `no_such_user_lastrun` (
  `timestamp` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
;

CREATE TABLE `no_such_user_rejections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` varchar(255) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `mta_host` varchar(255) NOT NULL,
  `mta_ip` varchar(15) NOT NULL,
  `timestamp` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`sender`,`recipient`,`mta_host`,`mta_ip`,`timestamp`),
  UNIQUE KEY `unique_message` (`sender`,`recipient`,`mta_host`,`mta_ip`,`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
;

INSERT `no_such_user_lastrun` (`timestamp`) VALUES (UNIX_TIMESTAMP(NOW()));
