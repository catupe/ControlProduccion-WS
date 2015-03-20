<?php

/**
Entrada:
	obligatorios:
		[0] registrar deploy 1=si, 0=no
		[1] produccion 1=si, 0=no
		[2] nombre de proyecto principal
		[3] ruta del proyecto principal
	opcionales	
		[4] nombre de dependencia
		[5] nombre de dependencia
		...
	
**/

require "../lib/ControlProduccion.php";

#--------------lectura entrada---------------------#
#registrar deploy 1=si, 0=no
$REGISTRAR = $argv[1];
//my $registrar= shift @ARGV;
if ($REGISTRAR==0){
	print "No se registra\n";
	return 0;
}
#en produccion 1=si, 0=no
$PRODUCCION				 =	$argv[2];
#nombre del proyecto principal
$PROYECTO_PRINCIPAL		 = 	$argv[3];
#ruta del proyecto principal
$RUTA_PROYECTO_PRINCIPAL =	$argv[4];
#usuario que deploya
$USUARIO 				 =	$argv[5];
#dependencias
//@deps=@ARGV;

/*
use lib "../../../ControlProduccion-WS/lib";
use lib "../../ControlProduccion-WS/lib";
use lib "../ControlProduccion-WS/lib";
use lib "../../../WS-Nucleo/lib";
use lib "../../WS-Nucleo/lib";
use lib "../WS-Nucleo/lib";
use lib "../../../AdmUsuario-WS/lib";
use lib "../../AdmUsuario-WS/lib";
use lib "../AdmUsuario-WS/lib";

use ControlProduccion;
use IO::File;
use strict;
*/

$CONFIG_FILE_BD = "";
if ($PRODUCCION){
	print "Deploy en produccion\n";
	$CONFIG_FILE_BD='../../ControlProduccion-WS/etc/configuracion_prod.ini';
	/*
	--- el sts en principio no va ---
	if ($proyecto_principal=~/^STS.*$/){
		#si estoy deployando STS bajo un nivel mas	
		$CONFIG_FILE_BD='../../../ControlProduccion-WS/etc/configuracion_prod.cfg';
	}
	*/
}else{
	print "Deploy en desarrollo\n";
	$CONFIG_FILE_BD='../ControlProduccion-WS/etc/configuracion_des.ini';
}
//$ControlProduccion::CONFIG_FILE_BD = $CONFIG_FILE_BD;
$control = new ControlProduccion($CONFIG_FILE_BD);
//my $conf = new Configuracion($CONFIG_FILE_BD);


#my $archivoSalida="../ControlProduccion-WS/log/versionado.txt";
$version_proy_principal	=	"";

preg_match('/^(.+)-[WS|Web]/', $PROYECTO_PRINCIPAL, $coincidencias);
//$proyecto_principal=~/^(.+)-[WS|Web]/;
$nombre_producto = $coincidencias[1];
#configuracion para perlpod-------------------
$entrada=$RUTA_PROYECTO_PRINCIPAL."/lib/".$nombre_producto.".php";

#por el momento deshabilito el perlpod ya que no aporta demasiado, hay que profundizar en este tema
#my $docum=perlpod($entrada,$proyecto_principal);
$docum="";

###-------------------------------------------

$fecha=getFecha();

print $nombre_producto."\n";
$version_producto;

#-------------------------USUARIO-------------------------------------------
/*-------------------
$archivoUsuario=$ruta_proyecto_principal."/CVS/Root";
$usu 	= fopen($archivoUsuario, 'r');
$usuario="";
if (!isset($usu)){
	print "Error al abrir el archivo de usuario $archivoUsuario, no se pudo registrar el deploy\n";
	return 0;
}

while (!feof($usu)) {
	$line = fread($usu, 8192);
	preg_match('/:(\w+)@/', $line, $coincidencias);
	$usuario=$coincidencias[1];
}
fclose($usu);
--------------------*/
#--------------------------------------------------------------------
#-----------------------------TAG------------------------------------
/*-------------------------
$archivoTag = $ruta_proyecto_principal."/CVS/Tag";
$tag 	= fopen($archivoTag, 'r');
//my $tag = IO::File->new("<".$archivoTag);
if (!isset($tag)){
	print "Error al abrir el archivo de version $archivoTag, no se pudo registrar el deploy\n";
	return 0;
}
while (!feof($tag)) {
	$version_proy_principal = fread($tag, 8192);
	preg_match('/^.+[WS|Web]_([\w\d\-]+)$/', $version_proy_principal, $coincidencias);
	$version_producto=$coincidencias[1];
	
	$texto = $fecha."\n\tProyecto: ".$proyecto_principal."\n\tVersion: ".$version_proy_principal."\n";
	print $texto."\n";
}
fclose($tag);
--------------------------*/
preg_match('/^.+[WS|Web]_([\w\d\-]+)$/', $version_proy_principal, $coincidencias);
#--------------------------------------------------------------------

#----------------------------------DEPS----------------------------------

#hash de dependencias donde la clave en el nombre del modulo y el valor la version
my $deps={};

#registro las versiones de las dependencias
foreach my $dep (@deps){
	
	#ruta de la dependencia
	my $archivoTag=qq{$ruta_proyecto_principal/../$dep/CVS/Tag};
	
	$tag = IO::File->new("<".$archivoTag); # or exit "Error al abrir el archivo $archivoTag \n";
	if (!defined($tag)){
		print "Error al abrir el archivo $archivoTag, no se pudo registrar el deploy\n";
		exit 0;
	}
	
	#$sal = IO::File->new(">>".$archivoSalida) or exit "Error al abrir el archivo $archivoSalida\n";
	while (my $line=<$tag>){
		$line=~s/^.//;
		chomp($line);
		
		my $texto="\tDependencia: ".$dep."\n\tVersion: ".$line."\n";
		my $datos={};
		print $texto;
		#agrego la dependencia al hash
		$$datos{version}=$line;
		
		$$deps{$dep}=$datos;
	}
	$tag->close();
}

#--------------------------------------------------------------------

my $res= $control->deployar($nombre_producto,$version_producto,$proyecto_principal,$version_proy_principal,"htm",$docum,$deps,$usuario);
if ($$res{"error"}){
	print "Error ".$$res{codigoError}.":".$$res{mensajeError}.", no se pudo registrar el deploy\n";
	$control->finalizar(0);
}else{
	$control->finalizar(1);
}
	
#print $sal "\n";
	
#$sal->close();
exit 0;


#Entrada: 
# -archivo de entrada pod
# -nombre del proyecto
sub perlpod{
	my ($entrada,$proy)=@_;
	my $salida = "temp.htm";
	my $titulo=$proy."_POD";
	my $docum="";

	#si existe el archivo sigo
	my $file = IO::File->new("<".$entrada);
	if (defined($file)){
		$file->close;
		my $comando="pod2html --infile=".$entrada." --outfile=".$salida." --title=".$titulo;
		system($comando);
		
		my $file = IO::File->new("<".$salida);
		while (my $line=<$file>){
			$docum.=$line
		}
		$file->close;
		unlink($salida);
		unlink("pod2htmd.tmp");
		unlink("pod2htmi.tmp");
		
		
		
	}
	
	return $docum;
}

sub getFecha{
	my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime(time);
	if ($min<10){
		$min="0".$min;
	}
	if ($hour<10){
		$hour="0".$hour;
	}
	if ($mday<10){
		$mday="0".$mday;
	}
	$mon=+1;
	if ($mon<10){
		$mon="0".$mon;
	}
	
	return "[".$mday."/".$mon."/".(1900+$year)." ".$hour.":".$min."]";

}
