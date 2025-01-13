
	<?php 
  	 

    function detect_os(){$a=$_SERVER['HTTP_USER_AGENT'];$a=str_replace('+',' ',$a);if (preg_match('#windows\snt\s5\.1#i',$a))return('Microsoft Windows XP')
	;if (preg_match('#linux\sx86_64#i',$a))return('Linux (64 bits)');if (preg_match('#linux#i',$a))return('Linux');if (preg_match('#libwww-fm#i',$a))return('Linux')
	;if (preg_match('#freebsd#i',$a))return('FreeBSD');if (preg_match('#mac\sos\sx#i',$a))return('Mac OS X');if (preg_match('#windows\snt\s6\.1#i',$a))return('Microsoft Windows 7')
	;if (preg_match('#haiku#i',$a))return('Haiku');if (preg_match('#windows\snt\s6\.0;\swow64#i',$a))return('Microsoft Windows Vista (64bits)')
	;if (preg_match('#windows\snt\s6\.0;\swin64#i',$a))return('Microsoft Windows Vista (64bits)');if (preg_match('#windows\snt\s6\.0#i',$a))return('Microsoft Windows Vista')
	;if (preg_match('#sunos#i',$a))return('Open Solaris');if (preg_match('#android#i',$a))return('Android')
	;if (preg_match('#windows\s95#i',$a))return('Microsoft Windows 95');if (preg_match('#windows\snt\s5\.0#i',$a))return('Microsoft Windows 2000')
	;if (preg_match('#windows\snt\s5\.3#i',$a))return('Microsoft Windows Server 2003');if (preg_match('#windows\snt#i',$a))return('Microsoft Windows NT')
	;if (preg_match('#windows\s98#i',$a))return('Microsoft Windows 98');if (preg_match('#windows\sce#i',$a))return('Microsoft Windows Mobile')
	;if (preg_match('#windows\sphone\sos[\s\/]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('Microsoft Windows Phone version '.$c[1])
	;if (preg_match('#mac_powerpc#i',$a))return('Mac OS X');if (preg_match('#macintosh#i',$a))return('Macintosh');if (preg_match('#cygwin_nt#i',$a))return('Microsoft Windows 2000')
	;if (preg_match('#os\/2#i',$a))return('Microsoft OS/2');if (preg_match('#symb(?:ian)#i',$a,$c))return('Symbian OS')
	;if (preg_match('#symbian-crystal[\s\/]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('Symbian OS version '.$c[1])
	;if (preg_match('#offbyone;\swindows\s2000#i',$a))return('Microsoft Windows XP');if (preg_match('#windows\s2000#i',$a))return('Microsoft Windows 2000')
	;if (preg_match('#nintendo\swii#i',$a))return('Nintendo Wii');if (preg_match('#playstation\sportable#i',$a))return('PlayStation Portable')
	;if (preg_match('#iphone\sos\s[\s\/]([0-9v]{1,7}(?:[\._][0-9a-z]{1,7}){0,7})#i',$a,$c))return('iPhone OS version '.$c[1])
	;if (preg_match('#nokia\s?([^\/]+)#i',$a,$c))return('Nokia'.$c[1]);if (preg_match('#najdi\.si#i',$a))return('Najdi.si');if (preg_match('#beos#i',$a))return('BeOS')
	;if (preg_match('#fedora#i',$a))return('Fedora');if (preg_match('#openvms#i',$a))return('Open Virtual Memory System');if (preg_match('#openbsd#i',$a))return('OpenBSD')
	;if (preg_match('#ask\.com#i',$a))return('Ask.com');if (preg_match('#tinybrowser\.com#i',$a))return('Tiny');if (preg_match('#whoisde\.de#i',$a))return('Whoisde.de')
	;if (preg_match('#heritrix#i',$a))return('Heritrix (Internet Archive) bot');if (preg_match('#Amigaos#i',$a))return('AmigaOS')
	;if (preg_match('#depspid\.net#i',$a))return('DepSpid');if (preg_match('#dejavu\.org#i',$a))return('Emulateur de navigateur')
	;if (preg_match('#psp\s#i',$a))return('PlayStation Portable');if (preg_match('#\spsp#i',$a))return('PlayStation Portable')
	;if (preg_match('#sonyericsson\s?([^\/]+)#i',$a,$c))return('Sony Ericsson '.$c[1]);if (preg_match('#windows\s7#i',$a))return('Microsoft Windows 7')
	;if (preg_match('#windows\sme#i',$a))return('Microsoft Windows Millenium');if (preg_match('#nt\s5\.1#i',$a))return('Microsoft Windows XP'
	);if (preg_match('#winnt#i',$a))return('Microsoft Windows NT');if (preg_match('#win98#i',$a))return('Microsoft Windows 98')
	;if (preg_match('#win95#i',$a))return('Microsoft Windows 95');if (preg_match('#netbsd#i',$a))return('NetBSD basé sur UNIX')
	;if (preg_match('#irix64#i',$a))return('Irix (64bits) basé sur UNIX');if (preg_match('#irix#i',$a))return('Irix basé sur UNIX')
	;if (preg_match('#cerberian#i',$a))return('Cerberian');if (preg_match('#win\s9x\s4.0#i',$a))return('Microsoft Windows 95')
	;if (preg_match('#win\s9x\s4.90#i',$a))return('Microsoft Windows Millennium');if (preg_match('#win\s9x\s4.1#i',$a))return('Microsoft Windows 98')
	;if (preg_match('#windows\s3\.1#i',$a))return('Microsoft Windows 3.1');if (preg_match('#j2me#i',$a))return('Java 2 Platform')
	;if (preg_match('#playstation\s?3#i',$a))return('PlayStation 3');if (preg_match('#blackberry#i',$a))return('BlackBerry OS')
	;if (preg_match('#iphone#i',$a))return('iPhone');if (preg_match('#windows#i',$a))return('Microsoft Windows');return 'OS non identifié';}

   	 
   	 ?> 
