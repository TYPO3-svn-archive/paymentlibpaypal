<?php

########################################################################
# Extension Manager/Repository config file for ext: "paymentlib_paypal"
#
# Auto generated 18-04-2008 16:01
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Paypal Payment Suite',
	'description' => 'Provides the possibility to transact payments via Paypal using the Payment Library extension.',
	'category' => 'misc',
	'author' => 'Franz Holzinger / Udo Gerhards',
	'author_email' => 'contact@fholzinger.com',
	'shy' => '',
	'dependencies' => 'paymentlib,paysuite,static_info_tables',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'alpha',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.0.4',
	'constraints' => array(
		'depends' => array(
			'php' => '5.1.2-0.0.0',
			'paymentlib' => '0.2.1-',
			'paysuite' => '0.0.1-',
			'static_info_tables' => '2.0.5-',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:14:{s:9:"ChangeLog";s:4:"6fd9";s:10:"README.txt";s:4:"9fa9";s:21:"ext_conf_template.txt";s:4:"48d1";s:12:"ext_icon.gif";s:4:"ebac";s:17:"ext_localconf.php";s:4:"2292";s:13:"locallang.php";s:4:"206e";s:18:"paymentmethods.xml";s:4:"cf3c";s:14:"doc/manual.sxw";s:4:"5506";s:42:"pi1/class.tx_paymentlibpaypal_provider.php";s:4:"2776";s:18:"res/paypal_big.gif";s:4:"77ff";s:19:"res/paypal_euro.gif";s:4:"f282";s:28:"res/paypal_international.gif";s:4:"832e";s:29:"res/paypal_poundssterling.gif";s:4:"3398";s:23:"res/paypal_verified.gif";s:4:"7a62";}',
	'suggests' => array(
	),
);

?>