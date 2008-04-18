<?php

########################################################################
# Extension Manager/Repository config file for ext: "paypal_suite"
#
# Auto generated 22-08-2006 10:43
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Paypal Payment Suite',
	'description' => 'Provides the possibility to transact payments via Paypal',
	'category' => 'misc',
	'author' => 'Udo Gerhards / Franz Holzinger',
	'author_email' => 'Udo.Gerhards@cms-solutions.info',
	'shy' => '',
	'dependencies' => '',
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
	'version' => '0.0.3',
	'constraints' => array(
		'depends' => array(
			'php' => '5.1.2-',
			'paymentlib' => '0.2.1-',
			'paysuite' => '',
			'static_info_tables' => '2.0.5-',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:19:{s:9:"ChangeLog";s:4:"1d00";s:10:"README.txt";s:4:"9fa9";s:21:"ext_conf_template.txt";s:4:"91d5";s:15:"ext_emconf.php~";s:4:"15fb";s:12:"ext_icon.gif";s:4:"1bdc";s:17:"ext_localconf.php";s:4:"b260";s:18:"ext_localconf.php~";s:4:"b260";s:13:"locallang.php";s:4:"206e";s:18:"paymentmethods.xml";s:4:"0680";s:19:"doc/wizard_form.dat";s:4:"b880";s:20:"doc/wizard_form.html";s:4:"75ae";s:34:"lib/class.tx_paymentlib_basket.php";s:4:"c8dd";s:42:"pi1/class.tx_paymentlibpaypal_provider.php";s:4:"8f07";s:13:"res/Thumbs.db";s:4:"249d";s:18:"res/paypal_big.gif";s:4:"77ff";s:19:"res/paypal_euro.gif";s:4:"f282";s:28:"res/paypal_international.gif";s:4:"832e";s:29:"res/paypal_poundssterling.gif";s:4:"3398";s:23:"res/paypal_verified.gif";s:4:"7a62";}',
	'suggests' => array(
	),
);

?>