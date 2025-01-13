    
	<?php 
	
	function detect_lang(){
    $a=$_SERVER['HTTP_ACCEPT_LANGUAGE'];$t['af']='Africain';$t['sq']='Albanais';$t['ar-dz']='Alg�rien';$t['de']='Allemand';$t['de-at']='Allemand (Austrian)'
	;$t['de-li']='Allemand (Liechtenstein)';$t['de-lu']='Allemand (Luxembourg)';$t['de-ch']='Allemand (Suisse)';$t['en-us']='Am�ricain';$t['en']='Anglais'
	;$t['en-za']='Anglais (Afrique du sud)';$t['en-bz']='Anglais (B�lize)';$t['en-gb']='Anglais (Grande Bretagne)';$t['ar']='Arabe';$t['ar-sa']='Arabe (Arabie Saoudite)'
	;$t['ar-bh']='Arabe (Bahre�n)';$t['ar-ae']='Arabe (Emirat arabe uni)';$t['en-au']='Australien';$t['eu']='Basque';$t['nl-be']='Belge';$t['be']='Bi�lorussie'
	;$t['bg']='Bulgarre';$t['en-ca']='Canadien';$t['ca']='Catalan';$t['zh']='Chinois';$t['zh-hk']='Chinois (Hong-Kong)';$t['zh-cn']='Chinois (PRC)'
	;$t['zh-sg']='Chinois (Singapourg)';$t['zh-tw']='Chinois (Ta�wan)';$t['ko']='Cor�ein';$t['cs']='Cr�te';$t['hr']='Croate';$t['da']='Danois'
	;$t['ar-eg']='Egyptien';$t['es']='Espagnol';$t['es-ar']='Espagnol (Argentine)';$t['es-bo']='Espagnol (Bolivie)';$t['es-cl']='Espagnol (Chilie)'
	;$t['es-co']='Espagnol (Colombie)';$t['es-cr']='Espagnol (Costa Rica)';$t['es-sv']='Espagnol (El Salvador)';$t['es-ec']='Espagnol (Equateur)'
	;$t['es-gt']='Espagnol (Guatemala)';$t['es-hn']='Espagnol (Honduras)';$t['es-mx']='Espagnol (Mexique)';$t['es-ni']='Espagnol (Nicaragua)'
	;$t['es-pa']='Espagnol (Panama)';$t['es-py']='Espagnol (Paraguay)';$t['es-pe']='Espagnol (P�rou)';$t['es-pr']='Espagnol (Puerto Rico)'
	;$t['en-tt']='Espagnol (Trinidad)';$t['es-uy']='Espagnol (Uruguay)';$t['es-ve']='Espagnol (Venezuela)';$t['et']='Estonien';$t['sx']='Estonien'
	;$t['fo']='Faeroese';$t['fi']='Finlandais';$t['fr']='Fran�ais';$t['fr-fr']='Fran�ais';$t['fr-be']='Fran�ais (Belgique)';$t['fr-ca']='Fran�ais (Canada)'
	;$t['fr-lu']='Fran�ais (Luxembourg)';$t['fr-ch']='Fran�ais (Suisse)';$t['gd']='Galicien';$t['el']='Gr�c';$t['he']='H�breux';$t['nl']='Hollandais'
	;$t['hu']='Hongrois';$t['in']='Indon�sien';$t['hi']='Indou';$t['fa']='Iranien';$t['ar-iq']='Iraquien';$t['en-ie']='Irlandais';$t['is']='Islandais'
	;$t['it']='Italien';$t['it-ch']='Italien (Suisse)';$t['en-jm']='Jamaicain';$t['ja']='Japonais';$t['ar-jo']='Jordanien';$t['ar-kw']='Koweitien'
	;$t['lv']='Lettische';$t['ar-lb']='Libanais';$t['lt']='Littuanien';$t['ar-ly']='Lybien';$t['mk']='Mac�doine';$t['ms']='Mal�sien';$t['mt']='Maltais'
	;$t['ar-ma']='Marocain';$t['en-nz']='N�o-z�landais';$t['no']='Norv�gien (bokmal)';$t['no']='Norv�gien (Nynorsk)';$t['ar-om']='Oman';$t['pl']='Polonais'
	;$t['pt']='Portugais';$t['pt-br']='Portugais (Br�sil)';$t['ar-qa']='Quatar';$t['rm']='Rhaeto-Romanic';$t['ro']='Roumain (Moldavie)'
	;$t['ro-mo']='Roumain (Moldavie)';$t['ru']='Russe';$t['ru-mo']='Russe (Moldavie)';$t['sr']='Serbe (Cyrillic)';$t['sr']='Serbe (Latin)';$t['sk']='Slovaque'
	;$t['sl']='Slov�ne';$t['sb']='Sorbian';$t['sv']='Su�dois';$t['sv-fi']='Su�dois (Finlande)';$t['ar-sy']='Syrien';$t['th']='Tha�landais'
	;$t['ts']='Tsonga (Afrique du sud)';$t['tn']='Tswana (Afrique du sud)';$t['ar-tn']='Tunisien';$t['tr']='Turc';$t['uk']='Ukrainien';$t['ur']='Urdu'
	;$t['vi']='Vietnamien';$t['xh']='Xhosa (Afrique)';$t['ar-ye']='Y�men';$t['ji']='Yiddish';$t['zu']='Zulu (Afrique)'
	;$a=strtolower(preg_replace('#^([a-z\-]+).*#','$1',$a));if (array_key_exists($a,$t)) return $t[$a]; return 'Langue inconnue';}


   	 ?> 