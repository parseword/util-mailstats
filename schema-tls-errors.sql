CREATE TABLE `tls_errors_lastrun` (
  `timestamp` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
;

CREATE TABLE `tls_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mta_host` varchar(255) NULL,
  `mta_ip` varchar(15) NULL,
  `timestamp` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`mta_host`,`mta_ip`,`timestamp`),
  UNIQUE KEY `unique_error` (`mta_host`,`mta_ip`,`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
;

/* Prime last run table with a timestamp from one day ago */
INSERT `tls_errors_lastrun` (`timestamp`) VALUES (UNIX_TIMESTAMP(NOW())-86400);
