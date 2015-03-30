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
			$this->adm_usuario 		= new AdmUsuario($ruta_configuracion, $ambiente);
			$this->basedatos 	 	= new Database($ruta_configuracion, $ambiente);
			
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
			
				$res = $this->_getIdVersionProyecto($nombre_proy,$version_proy);
				//return $res if ($$res{error});
				$id_version_proy = $res->id;
			
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
			
				$res = $this->_getIdVersionProyecto($proyecto,$version);
				$id_version_proy = $res[0]->id;
			
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
			
				$res = $this->_getIdVersionProducto($producto,$version);
				$id_ver_prod = $res[0]->id;
			
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
	}