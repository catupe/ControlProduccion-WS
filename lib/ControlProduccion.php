<?php

	include_once 'AdmUsuario.php';
	include_once 'MensajesControlProduccion.php';
	include_once '../AdmUsuario/AdmUsuario.php';
	include_once '../Web-Nucleo/Configuracion.php';
	
	
	class ControlProudccion{
		
		var $configuracion 	= null;
		var $adm_usuario 	= null;
		var $basedatos		= null;
		
		public function ControlProduccion($ruta_configuracion = "", $ambiente = ""){
			
			$this->configuracion 	= new Configuracion($ruta_configuracion, $ambiente);
			$this->maxTuplas		= $this->configuracion->getDato('maxTuplas');
			
			$this->adm_usuario 		= new AdmUsuario($ruta_configuracion, $ambiente);
			$this->basedatos 	 	= new Database($ruta_configuracion, $ambiente);
			
			$this->basedatos->BeginTransaction();
			$this->mensaje 				= Mensajes::getInstance();
		}
		
		public function finalizar(){
			print "\n====SALGO DE ADM USUARIO ====\n";
			print "----".$this->error ."----\n";
				
			if($this->error == 0){
				// si no hubo error commiteo
				$this->basedatos->CommitTransaction();
			}
			else{
				// si hubo error rollback
				$this->basedatos->RollBackTransaction();
			}
		}
		
		private function _getBaseDatos(){
			
		}
		
		public function _getIdVersionProyecto($proy,$version){
			try{
				$db = $this->basedatos;
			
				$consulta = ' select vp.id 						'.
							' from version_proy vp, proyecto p  '.
							' where vp.version = ?				'.
							' 		and p.nombre = ?			'.
							'		and vp.proyecto = p.id		';
			
				$res	= $this->basedatos->ExecuteQuery($consulta, array($version, $proy));
				$row	= $db->ejecutarSQL($consulta, $version, $proy);
				
				if(!isset($res[0]->id)){
					$mensaje = $this->mensaje->getMensaje('001', array());
					throw new Exception($mensaje , '001');					
				}
				
				return $res[0]->id;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		public function _getIdProyecto($proy) {
			try{
				$db = $this->basedatos;
			
				$consulta= ' select id from proyecto where nombre = ? ';
				$row	= $this->basedatos->ExecuteQuery($consulta, array($proy));
				
				if(!isset($res[0]->id)){
					$mensaje = $this->mensaje->getMensaje('003', array());
					throw new Exception($mensaje , '003');
				}
				
				return $row[0]->id;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		public function _getIdVersionProducto($prod,$version) {
			try{
				$db = $this->basedatos;
			
				$consulta = ' 	select vp.id						'.
							'	from version_prod vp, producto p	'.
							'	where vp.version = ?				'.
							'		  and p.nombre = ?				'.
							'		  and vp.producto = p.id		';
				
				$row	= $this->basedatos->ExecuteQuery($consulta, array($version, $proy));
				
				if (!isset($row[0]->id)){
					$mensaje = $this->mensaje->getMensaje('002', array());
					throw new Exception($mensaje , '002');					
				}
			
				return $row[0]->id;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		############# deploy automatico ####################################################################################3
		
		#Genera las entradas necesarias en la base para representar un deploy de un producto
		# Entrada:
		# -nombre del producto
		# -version del producto
		# -nombre del proyecto principal (o sea el que se deploya)
		# -version del proyecto principal
		# -data del documento a relacionar
		# -formato del documento
		# -dependencias: hash con claves los nombres de los proyectos y valor un hash
		# con clave "version" con la version, "documento" su documento asociado y "formato" el formato del mismo.
		# -usuario: usuario que realiza el deploy
		
		public function deployar($nombre_producto,$version_producto,
								 $proyecto_principal,$version_proy_principal,$formato,
								 $documento,$deps, $usuario){
			
			try{					 	
				$db = $this->basedatos;
					
				#agrego el producto, si ya exsite no lo agrega
				$res = $this->addProducto($nombre_producto);
				//return $res if ($$res{error});
			
				#agrego la version del producto, si ya existe no la agrega
				$res= $this->addVersionProducto($nombre_producto,$version_producto);
				//return $res if ($$res{error});
			
				#agrego el deploy, si ya existe no lo agrega
				$res= $this->addDeploy($nombre_producto, $version_producto, $usuario);
				//return $res if ($$res{error});
			
				#ahora agrego los modulos de la version del producto.
			
				#agrego el proyecto principal, si ya existe no lo agrega
				$res= $this->addProyecto($proyecto_principal);
				//return $res if ($$res{error});
			
				#agrego la version del proyecto principal, si ya existe no la agrega
				$res= $this->addVersionProyecto($proyecto_principal,$version_proy_principal);
				//return $res if ($$res{error});
			
				#si se paso un documento para agregar se lo agrega
				if (isset($documento) and $documento != ""){
					#agrego la documentacion de la version del proyecto principal, si ya existe lo actualiza
					$nombre 		= $proyecto_principal . "_POD";
					$descripcion 	= "";
					$res 			= $this->addDocumentoVersion($nombre,$descripcion,$proyecto_principal,$version_proy_principal,$formato,$documento);
					//return $res if ($$res{error});
				}
			
				#agrego la version del proyecto a la del producto, si ya existe no la agrega
				$res = $this->addProdProy($nombre_producto,$version_producto,$proyecto_principal,$version_proy_principal);
				//return $res if ($$res{error});
				$existia = $res->existia;
		
				# si ya existia esa version del proyecto principal para el producto
				# elimino todas las dependencias del mismo,
				# de esta forma permito el reporceso de un deploy
				# estoy suponiendo que siempre se genera una nueva version del proyecto principal para deployar
				if ($existia){
					$res 	= $this->eliminarDependencias($proyecto_principal,$version_proy_principal);
					//return $res if ($$res{error});
					$id_dep = $res->id;
				}
			
					#agrego cada dependencia
				if (isset($deps) and $deps != ""){
					//my %deps=%$deps;
					foreach($deps as $dep => $datos){
					//while (my ($dep, $datos)=each (%deps)){
						$ver = $datos->version;
						$doc = $datos->documento;
						$for = $datos->formato;
							
						#agrego el proyecto, si ya existe no lo agrega
						$res = $this->addProyecto($dep);
						//return $res if ($$res{error});
						$id_dep = $res->id;
							
						#agrego la version del proyecto, si ya existe no la agrega
						$res = $this->addVersionProyecto($dep,$ver);
						//return $res if ($$res{error});
						$id_ver_dep = $res->id;
							
						if (isset($doc) and $doc != ""){
						#agrego la documentacion de la version del proyecto principal, si exsiste lo actualiza
							$nombre		="Auto-Doc:".$dep;
							$descripcion="Auto-Doc:".$dep;
							$res		= $this->addDocumentoVersion($nombre,$descripcion,$dep,$ver,$for,$doc);
							//return $res if ($$res{error});
						}
			
						#agrego la dependencia con el proyecto principal
						$res = $this->addDependencia($proyecto_principal,$version_proy_principal,$dep,$ver);
						//return $res if ($$res{error});
					}
				}
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		#Asigna un documento a una version de un proyecto.
		#Si ya existe lo sustituye sino lo crea
		#Entrada:
		# -nombre del documento
		# -descripcion del documento
		# -nombre del proyecto
		# -version del proyecto
		# -formato del documento: por ejemplo "htm", "doc", etc
		# -dato: dato binario o string
		public function addDocumentoVersion($nombre_doc,$descripcion,$nombre_proy,$version_proy,$formato,$dato){
			try{
				$db = $this->_getBaseDatos();
			
				$id_version_proy = $this->_getIdVersionProyecto($nombre_proy,$version_proy);
				//return $res if ($$res{error});
				//$id_version_proy = $res->id;
			
				#verifico si existe
				$consulta = '	select id			'.
							'	from documento		'.
							'	where nombre = ?	'.
							'	and version_proy = ?';
				$row	= $db->ExecuteQuery($consulta, array($id_version_proy));
				#si ya existe lo sustituyo
				if (defined($row[0]->id)){
					$id_documento = $row[0]->id;
					$consulta = ' update documento set nombre=?, descripcion=?, formato=? where id=? ';
					$res = $this->basedatos->ExecuteNonQuery($consulta, array($nombre_doc,$descripcion,$formato,$id_documento), false);
					
					/*
					#lo elimino
					$consulta = ' delete from documento '.
								' where id = ? 			';
					$row	  = $db->ExecuteQuery($consulta, array($id_version_proy));
					//$sth = $db->ejecutarSQL($consulta,$$row{id})
					#lo creo nuevamente
					$id_documento = $row[0]->id;
					*/
				}
				//else{
				//	$id_documento=$self->_getNextNumerador("documento");
				//}
				else{		
					#lo agrego
					$consulta = ' insert into documento (nombre,descripcion, formato,version_proy, dato) values (?,?,?,?,?) ';
					//$sth = $db->ExecuteQuery($consulta, array($id_dkocumento,"nom","des","for",$id_version_proy,$dato)); 
					$res = $this->basedatos->ExecuteNonQuery($consulta, array("nom","des","for",$id_version_proy,$dato), false);
					//$db->ejecutarSQL($consulta,$id_documento,"nom","des","for",$id_version_proy,$dato)
					
					//+$consulta = ' update documento set nombre=?, descripcion=?, formato=? where id=? ';
				 	//+$res = $this->basedatos->ExecuteNonQuery($consulta, array($nombre_doc,$descripcion,$formato,$id_documento), false);
					//$sth = $db->ejecutarSQL($consulta,$nombre_doc,$descripcion,$formato,$id_documento)
				}			
				return 0;
			
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		#Elimina las dependencias de un proyecto
		#Entrada:
		# nombre del proyecto prin
		# version del proyecto principal (el dependiente)
		public function eliminarDependencias($proyecto,$version){
			try{
				$db = $this->basedatos;
			
				$id_version_proy = $this->_getIdVersionProyecto($proyecto,$version);
				//$id_version_proy = $res[0]->id;
			
				#elimino las dependencias del proyecto principal de la tabla dependencias
				$consulta = ' delete from dependencia 	'.
							' where version_proy = ? 	';
				
				$res = $this->basedatos->ExecuteNonQuery($consulta, array($id_version_proy), false);
				
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}

		#Crea un nuevo deploy salvo que ya exista
		#un deploy ya existe si coincide la version del producto a deployar
		#Entrada:
		#	nombre del producto
		#   version del producto
		#	usuario que realiza el deploy
		#Salida:
		#	id del deploy
		public function addDeploy($producto,$version,$usuario){
			try{
				$db = $this->basedatos;
			
				$id_ver_prod = $this->_getIdVersionProducto($producto,$version);
				//$id_ver_prod = $res[0]->id;
			
				$fecha = $this->_getFecha;
				#verifico que no exista el deploy, sino devuelvo el id
				$consulta = ' select id					'.
							' from deploy				'.
							' where version_prod = ?	';
				$row	= $this->basedatos->ExecuteQuery($consulta, array($version, $proy));
				
				if (isset($row[0]->id)){
					#solo actualizo la fecha ya que lo tomo como reproceso
					$consulta = ' update deploy set fecha=?, usuario=? where id=? ';
					$res = $this->basedatos->ExecuteNonQuery($consulta, array($fecha, $usuario, $row[0]->id), false);
					//$sth = $db->ejecutarSQL($consulta,$fecha,$usuario,$$row{id})
					
					return  $row[0]->id;
				}
				//$id_deploy = $self->_getNextNumerador("deploy");
				#creo el deploy
				$consulta = ' insert into deploy (id,version_prod,usuario)	values (?,?,?) ';
				$res = $this->basedatos->ExecuteNonQuery($consulta, array($id_deploy, $id_ver_prod, $usuario), false);
				//$sth = $db->ejecutarSQL($consulta,$id_deploy ,$id_ver_prod,$usuario)
				
				return $id_deploy;

			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		#Crea un nuevo producto salvo que ya exista
		#Entrada:
		#	nombre del producto
		#Salida:
		# id del producto
		public function addProducto($nombre){
			try{
				$db = $this->basedatos;
			
				#verifico que no exista el producto, sino devuelvo el id
				$consulta = ' select id			'.
							' from producto		'.
							' where nombre = ?	';
				$row	= $this->basedatos->ExecuteQuery($consulta, array($nombre));
				
				if (isset($row[0]->id)){
					return $row[0]->id;
				}
				//my $id=$self->_getNextNumerador("producto");
			
				#creo el producto
				$consulta = ' insert into producto (nombre)	values (?) ';
				$id = $this->basedatos->ExecuteNonQuery($consulta, array($nombre), true);
				return $id;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}

		#Crea un nuevo proyecto salvo que ya exista
		#Entrada:
		# nombre del proyecto
		#Salida:
		# id del proyecto
		public function addProyecto($nombre){
			try{
				$db = $this->basedatos;
			
				#verifico que no exista el proyecto, sino devuelvo el id
				$consulta = ' select id			'.
							' from proyecto		'.
							' where nombre = ?	';
				$row	= $this->basedatos->ExecuteQuery($consulta, array($nombre));
				
				if (isset($row[0]->id)){
					return $row[0]->id;
				}
				//my $id=$self->_getNextNumerador("proyecto");
	
				#creo el proyecto
				$consulta = ' insert into proyecto (nombre)	values (?) ';
				$id = $this->basedatos->ExecuteNonQuery($consulta, array($nombre), true);
				
				return $id;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		# Crea una dependencia de un proyecto hacia otro
		#Entrada:
		#	nombre proyecto dependiente
		# 	version del proyecto dependiente
		#	nombre del que depende
		# 	version del que depende
		public function addDependencia($nombre,$version,$nombre_dep,$version_dep){
			try{
				$db = $this->basedatos;
			
				$id 	= $this->_getIdVersionProyecto($nombre,$version);
				//$id  	= $res[0]->id;
			
				$id_dep 	= $this->_getIdVersionProyecto($nombre_dep,$version_dep);
				//$id_dep	= $res[0]->id;
			
				#verifico que no exista
				$consulta = ' 	select *						'.
							'	from dependencia				'.
							'	where version_proy = ? 			'.
							'		  and version_proy_dep = ?	';
				$row	= $this->basedatos->ExecuteQuery($consulta, array($id, $id_dep));
				if (isset($row[0]->version_proy)){
					return 0;
				}
	
				#creo la dependencia
				$consulta = ' insert into dependencia(version_proy, version_proy_dep) values (?,?) ';
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($id, $id_dep), false);
				
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}

		#Agrega un proyecto a un producto
		#Entrada:
		#	nombre del producto
		# 	version del producto
		#	nombre del proyecto
		# 	version del proyecto
		#Salida:
		# existia: 1 si ya existia, 0 sino
		public function addProdProy($producto, $version, $proyecto, $version_proyecto){
			try{
				$db = $this->basedatos;
			
				$id_prod = $this->_getIdVersionProducto($producto,$version);
				//$id_prod = $res[0]->id;
			
				$id_proy= $this->_getIdVersionProyecto($proyecto,$version_proyecto);
				//return $res if ($$res{error});
				//$id_proy = $res[0]->id;
			
				#verifico que no exista
				$consulta = ' select *					 '.
							' from version_prod_proy	 '.
							' where version_proy = ?	 '.
							'		and version_prod = ? ';
				$row = $this->basedatos->ExecuteQuery($consulta, array($id_proy, $id_prod));
				
				$salida = new stdClass();
				$salida->existia = 0;
				
				if (isset($row[0]->version_proy)){
					$salida->existia = 1;
					return $salida;
				}

				#la creo
				$consulta = ' insert into version_prod_proy (version_prod, version_proy) values (?,?) ';
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($id_prod, $id_proy), false);
				
				return $salida;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		# Agrega una version del proyecto si no existe
		#Entrada:
		# - nombre del proyecto
		# - version del proyecto
		#Salida:
		# -id
		public function addVersionProyecto($proyecto, $version){
			try{
				$db = $this->basedatos;
			
				$id_proy = $this->_getIdProyecto($proyecto);
				
				$consulta = ' 	select id			'.
							'	from version_proy	'.
							'	where proyecto = ?	'.
							'	and version = ?		';	
				$row = $this->basedatos->ExecuteQuery($consulta, array($id_proy, $version));
				
				#si ya existe retorno
				if (isset($row[0]->id)){
					return $row[0]->id;
				}
			
				//my $id_version_proy=$self->_getNextNumerador("version_proy");
			
				$consulta = ' insert into version_proy (id, proyecto, version) values (?,?,?) ';
				$id_version_proy = $this->basedatos->ExecuteNonQuery($consulta, array($id_version_proy, $id_proy, $version), true);
				
				return $id_version_proy;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		# Agrega una version de un producto si no existe
		#Entrada:
		#	-nombre producto
		#	-version del producto
		#Salida:
		# 	-id
		public function addVersionProducto($producto,$version){
			try{
						
				$id_prod= $this->_getIdProducto($producto);
			
				$consulta = ' 	select id				'.
							'	from version_prod		'.
							'	where producto = ?		'.
							'		  and version = ? 	';
				$row = $this->basedatos->ExecuteQuery($consulta, array($id_prod, $version));
				
				if (isset($row[0]->id)){
					return $row[0]->id;
				}
			
				//my $id_version_prod=$self->_getNextNumerador("version_prod");
			
				$consulta = ' insert into version_prod (id, producto, version) values (?,?,?) ';
				$id_version_prod = $this->basedatos->ExecuteNonQuery($consulta, array($id_version_prod, $id_prod, $version), true);
				
				return $id_version_prod;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		
		public function getDocumentos($id,$nombre, $version_proy){
			try{

				$where = "";
				$valores = array();
				if (isset($id) and ($id != "")){
					if (!(preg_match('/^\d+$/', $id))){
						$mensaje = $this->mensaje->getMensaje('005', array());
						throw new Exception($mensaje , '005');					
					}
			
					$where .= ' and v.id = ? ';
					$valores[] = $id;
				}
				if (isset($nombre) and ($nombre != "")){
					$where .= ' and upper(d.nombre) like upper(?) ';
					$valores[] = "%".$nombre."%";
				}
				if (isset($version_proy) and ($version_proy != "")){
					$where .= ' and v.version = ? ';
					$valores[] = $version_proy;
				}
			
				$consulta = ' 	select 	count(*)	as cantidad		'.
							'	from documento d, version_proy v	'.
							'	where d.version_proy = v.id 		';
					
				$row = $this->basedatos->ExecuteQuery($consulta.$where, $valores);
				
				$cant = $row[0]->cantidad;
				$msg = "";
				if ($cant > $this->maxTuplas){
					//$msg=$MENSAJE_ERROR{"S006"};
					//$msg=~s/CANT/$self->{maxTuplas}/;
					$msg = $this->mensaje->getMensaje('006', array('CANT'=>$this->maxTuplas));
				}
			
				$consulta = '	select first $self->{maxTuplas}		'.
							'		   	d.id,						'.
							'			d.nombre,					'.
							'			d.descripcion,				'.
							'			d.fecha_creacion,			'.
							'			d.formato,					'.
							'			v.version					'.
							'	from documento d, version_proy v	'.
							'	where d.version_proy = v.id			';

				$order = 	' order by d.nombre desc ';
				$row = $this->basedatos->ExecuteQuery($consulta.$where.$order, $valores);
				
				$salida;
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k=>$data){
					$salida[$data->id] = $data;
				}
			
				$sal = new stdClass();
				$sal->docs = $salida;
				$sal->msg  = $msg;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}	
		}
		public function getDocumento($id){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = '	select 	d.nombre,								'.
							'			d.descripcion,							'.
							'			d.formato,								'.
							'			d.dato,									'.
							'			p.nombre proyecto,						'.
							'			v.version								'.
							'	from documento d, proyecto p, version_proy v	'.
							'	where p.id=v.proyecto							'.
							'			and d.version_proy=v.id					'.
							'			and d.id=?								';
				$row = $this->basedatos->ExecuteQuery($consulta, array($id));
				
				$salida = new stdClass();
				$salida->doc = $row[0];
				return $salida;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getDeploy($id){		
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = '	select 	d.fecha,							'.
							'			d.observaciones,					'.
							'			v.version,							'.
							'			p.nombre producto					'.
							'	from deploy d, version_prod v, producto p	'.
							'	where v.producto = p.id						'.
							'			and d.version_prod = v.id			'.
							'			and d.id = ? 						';

				$row = $this->basedatos->ExecuteQuery($consulta, array($id));
				
				$salida = new stdClass();
				$salida->deploy = $row[0];
				return $salida;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getProducto($id){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
		
				$consulta = ' 	select 	nombre,	descripcion, activo '.
							'	from producto						'.
							'	where id = ? 						';

				$row = $this->basedatos->ExecuteQuery($consulta, array($id));
				
				$salida = new stdClass();
				$salida->producto = $row[0];
				return $salida;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getProyecto($id){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = ' 	select 	nombre,	descripcion		'.
							'	from proyecto					'.
							'	where id = ? 					';
				$row = $this->basedatos->ExecuteQuery($consulta, array($id));
				
				$salida = new stdClass();
				$salida->proyecto = $row[0];
				return $salida;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getVersionProyecto($id){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = '	select 	version, descripcion	'.
							'	from version_proy				'.
							'	where id = ? 					';
					
				$row = $this->basedatos->ExecuteQuery($consulta, array($id));
				
				$salida = new stdClass();
				$salida->version_proy = $row[0];
				return $salida;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getVersionProducto ($id){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = '	select 	version, descripcion	'.
							'	from version_prod				'.
							'	where id = ? 					';
					
				$row = $this->basedatos->ExecuteQuery($consulta, array($id));
				
				$salida = new stdClass();
				$salida->version_prod = $row[0];
				
				return $salida;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}			
		}
		public function setDeploy($id, $obs){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = ' update deploy set observaciones=? where id=? ';
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($obs, id), false);
				
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}	
		}
		public function setProducto($id, $des, $activo){
			try{		
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = ' update producto set descripcion=?, activo=? where id=? ';
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($des, $activo, $id), false);
				
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function setProyecto($id, $des){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = ' update proyecto set descripcion=? where id=? ';
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($des,$id), false);
				
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function setVersionProyecto($id, $des){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = ' update version_proy set descripcion=? where id=? ';
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($des,$id), false);
			
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function setVersionProducto($id, $des){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
					
				$consulta = ' update version_prod set descripcion=? where id=? ';
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($des,$id), false);
				
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}	
		}
		public function getProductos($id){
			try{
				$where="";
				$valores = array();
				
				if (isset($id) and ($id != "")){
					if (!(preg_match('/^\d+$/', $id))){
						$mensaje = $this->mensaje->getMensaje('005', array());
						throw new Exception($mensaje , '005');
					}
			
					$where .= ' and producto.id=? ';
					$valores[] = $id;
				}
			
				$consulta = ' 	select 	id,				'.
							'			nombre,			'.			
							'			descripcion,	'.
							'			fecha_creacion,	'.
							'			activo			'.
							'	from producto			'.
							'	where 1=1 				';
					
				$row = $this->basedatos->ExecuteQuery($consulta.$where, $valores);
				
				$salida = array();
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$salida[$dato->id] = $dato;
				}
			
				$sal = new stdClass();
				$sal->productos = $salida;
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getDeploys($desde, $hasta){
			try{	
				$where = "";
				$valores = array();
				
				if (isset($desde) and ($desde != "")){
					$where .= ' and to_char(d.fecha,"%Y%m%d")>=? ';
					$valores[] = $desde;
				}
				if (isset($hasta) and ($hasta != "")){
					$where .= ' and to_char(d.fecha,"%Y%m%d")<=? ';
					$valores[] = $hasta;
				}
			
				$consulta = '	select 	v.id,								'.
							'			p.nombre producto,					'.
							'			v.version version,					'.
							'			d.observaciones,					'.
							'			d.fecha,							'.
							'			d.id id_deploy,						'.
							'			d.usuario							'.
							'	from deploy d, version_prod v, producto p	'.
							'	where d.version_prod=v.id					'.
							'		  and v.producto=p.id 					';
					
				$row = $this->basedatos->ExecuteQuery($consulta.$where, $valores);
				
				$salida;
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$salida[$dato->id] = $dato;
				}
			
				$sal = new stdClass();
				$sal->deploys = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getProyectos($id){
			try{

				$where = "";
				$valores = array();
				
				if (isset($id) and ($id != "")){
					if (!(preg_match('/^\d+$/', $id))){
						$mensaje = $this->mensaje->getMensaje('005', array());
						throw new Exception($mensaje , '005');
					}
			
					$where .= ' and d.id=? ';
					$valores[] = $id;
				}
			
				$consulta = '	select 	id,				'.
							'			nombre,			'.
							'			descripcion,	'.
							'			fecha_creacion	'.
							'	from proyecto			'.
							'	where 1=1 				';
					
				$row = $this->basedatos->ExecuteQuery($consulta.$where, $valores);
				
				$salida;
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$salida[$dato->id] = $dato;
				}
			
				$sal = new stdClass();
				$sal->proyectos = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getVersionesProyecto($id){
			try{
				
				$where = "";
				$valores = array();
				
				if (isset($id) and ($id != "")){
					if (!(preg_match('/^\d+$/', $id))){
						$mensaje = $this->mensaje->getMensaje('005', array());
						throw new Exception($mensaje , '005');
					}
			
					$where .= ' and p.id=? ';
					$valores[] = $id;
				}
			
				$consulta = '	select 	v.id,					'.
							'			v.version,				'.
							'			v.fecha_creacion,		'.
							'			v.descripcion,			'.
							'			p.nombre				'.
							'	from version_proy v, proyecto p	'.
							'	where v.proyecto = p.id			';
				
				$row = $this->basedatos->ExecuteQuery($consulta.$where, $valores);
				
				$salida;
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					#obtengo las dependencias
					$consulta2 = ' 	select v.id id_version,p.nombre,v.descripcion,v.version	'.
								 '	from proyecto p, version_proy v, dependencia d			'.
								 '	where d.version_proy = ?								'.				
								 '			and d.version_proy_dep=v.id						'.
								 '			and p.id=v.proyecto								';
					
					$row2 = $this->basedatos->ExecuteQuery($consulta2, array($dato->id));
					
					$deps;
					//while (my $row2=$sth2->fetchrow_hashref){
					foreach($row2 as $k2 => $dato2){
						$deps[$dato2->id_version] = $dato2;
					}
					$dato['dependencias'] = $deps;
			
					$salida[$dato->id] = $dato;
				}
			
				$sal = new stdClass();
				$sal->versiones = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getVersiones($id){
			try{
				$consulta = ' 	select 	*			'.
							'	from version_proy	';
					
				$row = $this->basedatos->ExecuteQuery($consulta, array());
				
				$salida;
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$salida[$dato->id] = $dato;
				}
			
				$sal = new stdClass();
				$sal->versiones = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getVersionesProducto($id){
			try{
				$where = "";
				$valores = array();
				
				if (isset($id) and ($id != "")){
					if (!(preg_match('/^\d+$/', $id))){
						$mensaje = $this->mensaje->getMensaje('005', array());
						throw new Exception($mensaje , '005');
					}
			
					$where .= ' and p.id=? ';
					$valores[] = $id;
				}
			
				$consulta = '	select 	v.id,					'.
							'			v.version,				'.
							'			v.descripcion,			'.
							'			v.fecha_creacion,		'.
							'			p.nombre				'.
							'	from version_prod v, producto p	'.
							'	where v.producto = p.id			';
					
				$row = $this->basedatos->ExecuteQuery($consulta.$where, $valores);
				
				$salida = array();
				//while (my $row=$sth->fetchrow_hashref){
				foreach ($row as $k => $dato){
					$salida[$dato->id] = $dato;
				}
			
				$sal = new stdClass();
				$sal->versiones = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function delDocumento($id){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				$consulta = '	delete documento	'.
							'	where id = ?		';
					
				$row = $this->basedatos->ExecuteNonQuery($consulta, array($id), false);
				
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		#devuelve las versiones de los productos que estan en produccion
		public function getProduccion(){
			try{
				
				#ultimos deploys de poductos activos
				$consulta = '	select p.nombre, max(d.fecha) fecha			'.
							'	from producto p, version_prod v, deploy d	'.
							'	where p.id=v.producto						'.
							'			and v.id=d.version_prod				'.
							'			and p.activo=1						'.
							'	group by 1									';
				
				$row = $this->basedatos->ExecuteQuery($consulta, array());
				
				$salida = array();
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$consulta2 = '	select d.id id_deploy, d.observaciones, d.fecha,d.usuario,						'.
								 '	p.nombre, p.descripcion descripcion_p, v.version, v.descripcion descripcion_v,	'.
								 '	v.id id_version																	'.
								 '	from deploy d, version_prod v, producto p										'.
								 '	where d.version_prod=v.id														'.
								 '			and p.id=v.producto														'.
								 '			and d.fecha=?															'.
								 '			and p.nombre=?															';	
					
					$row2 = $this->basedatos->ExecuteQuery($consulta2, array($dato->fecha, $dato->nombre));
					
					$salida[$row2[0]->id_deploy]=$row2[0];
				}
				$sal = new stdClass();
				$sal->produccion = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}	
		}
		#devuelve los proyectos principales y sus dependencias de una version de producto
		public function getProyectosPrincipales($id){
			try{
				if (!(preg_match('/^\d+$/', $id))){
					$mensaje = $this->mensaje->getMensaje('005', array());
					throw new Exception($mensaje , '005');
				}
			
				#obtengo los ids de los proyectos principales
				$consulta = ' 	select vpr.id id_ver_proyecto,pr.nombre,vpr.descripcion,vpr.version	'.
							'	from proyecto pr, version_prod_proy vpp,							'.
							'		 version_proy vpr												'.
							'	where vpp.version_prod=?											'.
							'			and vpr.id=vpp.version_proy									'.
							'			and pr.id=vpr.proyecto										';
					
				$row = $this->basedatos->ExecuteQuery($consulta, array($id));
				
				$salida = array();
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					#obtengo las dependencias
					$consulta2 = '	select v.id id_version,p.nombre,v.descripcion,v.version	'.
								 '	from proyecto p, version_proy v, dependencia d			'.
								 '	where d.version_proy=?									'.
								 '			and d.version_proy_dep=v.id						'.
								 '			and p.id=v.proyecto								';
					
					$row2 = $this->basedatos->ExecuteQuery($consulta2, array($dato->id_ver_proyecto));
					
					$deps = array();
					//while (my $row2=$sth2->fetchrow_hashref){
					foreach($row2 as $k => $dato2){
						$deps[$dato2->id_version] = $dato2;
					}
					$dato["dependencias"] = $deps;
					
					$salida[$dato->id_ver_proyecto] = $dato;
				}
				
				$sal = new stdClass();
				$sal->principales = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function setDocumentoVersion($id,$nombre,$descripcion,$formato,$dato){
			try{
				
				$consulta = "";
				
				$consulta = '	update documento				'.
							'	set nombre=?, descripcion=?		'.
							'	where id = ?					';
				
				$sth = $this->basedatos->ExecuteNonQuery($consulta, array($nombre,$descripcion,$id), true);
				
				if (isset($formato) and $formato != ""){
					$consulta = '	select nombre, descripcion, version_proy	'.
								'	from documento								'.
								'	where id = ? 								';
					$row = $this->basedatos->ExecuteQuery($consulta, array($id));
					
					$nombre_o		= $row[0]->nombre;
					$descripcion_o	= $row[0]->descripcion;
					$version_proy_o	= $row[0]->version_proy;
			
					#lo elimino
					$consulta = '	delete from documento		'.
								'	where id = ?				';
					$sth = $this->basedatos->ExecuteNonQuery($consulta, array($id), false);
					
					#creo el documento nuevamente con mismo id
					$consulta = ' insert into documento (id, nombre,descripcion, formato,version_proy, dato) values (?,?,?,?,?,?) ';
					$sth = $this->basedatos->ExecuteNonQuery($consulta, array($id,"nom","des","for",$version_proy_o,$dato), true);
					
					$consulta = ' update documento set nombre=?, descripcion=?, formato=? where id=? ';
					$sth = $this->basedatos->ExecuteNonQuery($consulta, array($nombre_o,$descripcion_o,$formato,$id), true);
					//$sth = $db->ejecutarSQL($consulta,$nombre_o,$descripcion_o,$formato,$id)															
				}
				return 0;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getPlantillas($id){
			try{
				
				$consulta = '	select 	*		'.
							'	from plantilla	';
				$row = $this->basedatos->ExecuteQuery($consulta, array());
					
				$salida = array();
				$key = 'a';
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$salida[$key] = $dato;
					$key++;
				}
			
				$sal = new stdClass();
				$sal->plantillas = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getInstalaciones($prefijo){
			try{
				
				$prefijo = "%".$prefijo."-%";
			
				$consulta = '	select 	id,					'.
							'			nombre,				'.
							'			descripcion,		'.
							'			fecha_creacion,		'.
							'			activo				'.
							'	from producto				'.
							'	where nombre like ? 		';
					
				$row = $this->basedatos->ExecuteQuery($consulta, array($prefijo));
				
				$salida = array();
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$salida[$dato->id] = $dato;
				}
				
				$sal = new stdClass();
				$sal->instalaciones = $salida;
				
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
		public function getInstalacionesProduccion($prefijo){
			try{
				
				$prefijo="%".$prefijo."-%";
			
				#ultimos deploys de poductos activos
				$consulta = '	select p.nombre, max(d.fecha) fecha			'.
							'	from producto p, version_prod v, deploy d	'.
							'	where p.id=v.producto						'.
							'			and v.id=d.version_prod				'.
							'			and p.activo=1						'.
							'			and p.nombre like ?					'.
							'	group by 1									';
				
				$row = $this->basedatos->ExecuteQuery($consulta, array($prefijo));
				
				$salida = array();
				//while (my $row=$sth->fetchrow_hashref){
				foreach($row as $k => $dato){
					$consulta2 = '	select d.id id_deploy, d.observaciones, d.fecha,d.usuario,								'.
								 '		   p.nombre, p.descripcion descripcion_p, v.version, v.descripcion descripcion_v,	'.
								 '		   v.id id_version																	'.
								 '	from deploy d, version_prod v, producto p												'.
								 '	where d.version_prod=v.id																'.
								 '			and p.id=v.producto																'.
								 '			and d.fecha=?																	'.
								 '			and p.nombre=?																 	';
					
					$row2 = $this->basedatos->ExecuteQuery($consulta2, array($dato->fecha, $dato->nombre));
					$salida[$row2[0]->id_deploy] = $row2[0];
				}
				
				$sal = new stdClass();
				$sal->instalaciones = $salida;
				return $sal;
			}
			catch(Exception $e){
				$this->error = 1;
				throw new Exception( $e->getMessage( ) , (int)$e->getCode( ) );
			}
		}
	}