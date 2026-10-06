<?php

// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Registrar errores en archivo
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Iniciar sesión
session_start();


// Configurar CORS solo para el dominio autorizado
header("Access-Control-Allow-Origin: https://aulaelectrica.com");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Ruta base de la base de datos
$ruta_db = __DIR__ . "/CORREOS.db";




// Claves API
$env = parse_ini_file(__DIR__ . "/.private/clave.env");
if (!$env || !isset($env["OPENAI_KEY"]) || !isset($env["YOUTUBE_KEY"])) {
    http_response_code(500);
    error_log("❌ No se pudo cargar el archivo .env o faltan claves OPENAI_KEY/YOUTUBE_KEY");
    echo json_encode(["error" => "No se pudieron cargar las claves de API"]);
    exit;
}
$api_key = $env["OPENAI_KEY"];






// Procesar solicitudes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents("php://input");
    $datos = json_decode($json, true);
    $modo = $datos['modo'] ?? '';

    header('Content-Type: application/json');

    if ($modo === 'login') {
        login();
    } elseif ($modo === 'logout') {
        logout();
    } elseif ($modo === 'verificar_sesion') {
        verificar_sesion();
    } elseif ($modo === 'metadatos') {
        obtener_metadatos();
    } elseif ($modo === 'natural') {
        // BORRAMOS EL ARCHIVO DE LOGs error_log.txt
        @unlink(__DIR__ . '/error_log.txt'); // 🧹 Borrar log 

        traducir_a_sql();
    } elseif ($modo === 'execute_query') {
		execute_query();
    } else {
        echo json_encode(["error" => "Modo no reconocido"]);
        http_response_code(400);
    }

    exit;
}


// ==================================================
// ABRE LA CONEXIÓN CON LA BASE DE DATOS SQLITE Y REGISTRA FUNCIONES PERSONALIZADAS
// ==================================================
function ABRIR_CONEXION_SQLITE($ruta_db) {
    // 1. Crear conexión SQLite
    $db = new SQLite3($ruta_db);

    // 2. Opciones de rendimiento recomendadas
    $db->busyTimeout(5000); // Espera hasta 5 segundos si hay bloqueo
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('PRAGMA temp_store = MEMORY');

    // 3. Registrar función REGEXP para búsquedas por palabra exacta
    $db->createFunction('REGEXP', function ($pattern, $text) {
        if (!is_string($text)) return 0;

        // 3.1 Eliminar comodines SQL si se han colado
        $pattern = str_replace('%', '', $pattern);

        // 3.2 Desescapar barras dobles (\\b → \\b real)
        $pattern = stripcslashes($pattern);

        // 3.3 Ejecutar búsqueda con límites de palabra (insensible a mayúsculas y UTF-8)
        // AÑADIDA LA 'u' AL FINAL (/iu)
        return preg_match("/$pattern/iu", $text);
    }, 2);

    // 4. Devolver conexión activa
    return $db;
}



// ==================================================
// OBTENER_EMAILS_SESION_ESCAPADOS
// OBTIENE Y ESCAPA LOS EMAILS DE LA SESIÓN PARA USO SEGURO EN SQL.
// ==================================================
function OBTENER_EMAILS_SESION_ESCAPADOS($db) {
    if (!isset($_SESSION['emails']) || !is_array($_SESSION['emails']) || empty($_SESSION['emails'])) {
        error_log("[ERROR] No hay emails válidos en la sesión");
        return [];
    }

    return array_map(function($email) use ($db) {
        return "'" . $db->escapeString($email) . "'";
    }, $_SESSION['emails']);
}

// ==================================================
// Función que crea la tabla temporal usando una conexión ya abierta
// ==================================================
function CREAR_TABLA_PERMITIDOS_CONEXION($db) {
    // Si el rol es profesor, no crear tabla ni filtrar nada
    if ($_SESSION['acceso_seccion'] === 'profesor') {
        error_log("🟡 Rol profesor: no se crea tabla 'correos_permitidos'. Acceso total.");
        return -1; // Indicador de acceso completo
    }

    // Verificar que hay emails válidos en la sesión
    if (!isset($_SESSION['emails']) || empty($_SESSION['emails'])) {
        error_log("❌ No hay emails en la sesión para crear la tabla temporal.");
        return 0;
    }

    // Marcar inicio de cronometraje
    $inicio = microtime(true);

    try {
        // Escapar los emails y formatearlos como lista SQL
		$emails = OBTENER_EMAILS_SESION_ESCAPADOS($db);

        // Unir los emails en un string separado por comas
        $lista_emails = implode(',', $emails);

        // Borrar la tabla temporal si ya existía
        $db->exec("DROP TABLE IF EXISTS correos_permitidos");

        // Crear la tabla temporal filtrando por los emails de la sesión
        $sql = "CREATE TEMP TABLE correos_permitidos AS
                SELECT correo_id FROM correos WHERE email IN ($lista_emails)";
        $db->exec($sql);

        // Contar cuántos registros tiene la tabla temporal
        $count = $db->querySingle("SELECT COUNT(*) FROM correos_permitidos");

        // Medir duración del proceso
        $duracion = round((microtime(true) - $inicio) * 1000);

        // Registrar log con el resultado
        error_log("✅ Tabla 'correos_permitidos' creada con $count correo_id en {$duracion} ms.");

        // Devolver cuántos registros contiene
        return $count;

    } catch (Exception $e) {
        // Registrar error si algo falla
        error_log("❌ Error creando tabla temporal: " . $e->getMessage());
        return 0;
    }
}


// ==================================================
// Función para manejar el login
// ==================================================
function login() {

    // 📥 Leer el cuerpo de la solicitud (JSON recibido desde la interfaz)
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    // ✅ Obtener usuario y contraseña
    $usuario = $data['usuario'] ?? '';
    $contraseña = $data['contraseña'] ?? '';

    // ⚠️ Validar que no estén vacíos
    if (empty($usuario) || empty($contraseña)) {
        echo json_encode(["message" => "Usuario y contraseña son requeridos"]);
        http_response_code(400); // Error 400: falta información
        exit;
    }

    // 📡 Enviar datos al sistema de usuarios externo
    $url = "https://aulaelectrica.com/usuarios.php";
    $post_data = [
        "action" => "login",
        "email" => $usuario,
        "password" => $contraseña
    ];

    $options = [
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/x-www-form-urlencoded",
            "content" => http_build_query($post_data)
        ]
    ];

    // 📤 Hacer la solicitud y recoger respuesta
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    // 🔍 Interpretar respuesta del sistema de usuarios
    $resultado = json_decode($response, true);

    // ✅ Si login correcto, continuar
    if (isset($resultado["success"]) && $resultado["success"]) {

        // BORRAMOS EL ARCHIVO DE LOGs error_log.txt
        @unlink(__DIR__ . '/error_log.txt'); // 🧹 Borrar log 

        session_regenerate_id(true); // 🛡️ Previene fijación de sesión

        $_SESSION['usuario'] = $usuario;
        $_SESSION['acceso_seccion'] = $resultado["acceso_seccion"];
        $_SESSION['nombre'] = $resultado["nombre"] ?? '';

        // 📨 Obtener y dividir todos los emails (pueden estar separados por ; o espacios)
        $emails_crudos = $resultado["email"] ?? '';
        $_SESSION['emails'] = preg_split('/[;\s]+/', $emails_crudos, -1, PREG_SPLIT_NO_EMPTY);

        // 🔎 Verificar que todos los emails existen en la base de datos de correos, excepto si es profesor
        if ($_SESSION['acceso_seccion'] !== 'profesor') {
            $db = new SQLite3(__DIR__ . "/CORREOS.db");

            foreach ($_SESSION['emails'] as $email) {
                $stmt = $db->prepare("SELECT 1 FROM correos WHERE email = ? LIMIT 1");
                $stmt->bindValue(1, $email, SQLITE3_TEXT);
                $res = $stmt->execute();
                if (!$res->fetchArray()) {
                    echo json_encode(["message" => "El email '$email' no está presente en la base de datos de correos"]);
                    http_response_code(403); // Prohibido
                    exit;
                }
            }
        }

        // ✅ Todo correcto, devolver datos del usuario
        echo json_encode([
            "message" => "Autenticación exitosa",
            "acceso_seccion" => $_SESSION['acceso_seccion'],
            "nombre" => $_SESSION['nombre'],
            "emails" => $_SESSION['emails']
        ]);
        http_response_code(200); // OK

    } else {
        // ❌ Login fallido por usuario o contraseña
        echo json_encode(["message" => "Usuario o contraseña incorrectos"]);
        http_response_code(401); // No autorizado
    }
}



// ==================================================
// Función para cerrar sesión
// ==================================================
function logout() {
    // Eliminar todas las variables de sesión
    session_unset();
    // Destruir la sesión por completo
    session_destroy();

    // Confirmar cierre
    echo json_encode(["message" => "Sesión cerrada"]);
    http_response_code(200); // OK
}


// ==================================================
// Función para verificar si hay sesión activa
// ==================================================
function verificar_sesion() {
    // Verifica si el usuario está en la sesión
    if (!isset($_SESSION['usuario'])) {
        echo json_encode(["message" => "Sin sesión activa"]);
    } else {
        // Preparar nombre y emails si existen
        $nombre = $_SESSION['nombre'] ?? '';
        $emails = $_SESSION['emails'] ?? [];

        // Confirmar sesión activa y devolver también los datos asociados
        echo json_encode([
            "message" => "Sesión activa",
            "usuario" => $_SESSION['usuario'],
            "nombre" => $nombre,
            "acceso_seccion" => $_SESSION['acceso_seccion'],
            "emails" => $emails
        ]);
    }
}




function formatear_estructura_base_datos($estructura_db) {
    $texto = "Estructura de la base de datos:\n";
    foreach ($estructura_db as $tabla => $columnas) {
        $texto .= "Tabla {$tabla} (los siguientes campos pertenecen a esta tabla):\n";
        foreach ($columnas as $nombre) {
            $texto .= "- {$nombre} (en {$tabla})\n";
        }
        $texto .= "\n";
    }
    return $texto;
}








// ==================================================
// Función para traducir una consulta en lenguaje natural a SQL
// ==================================================
function traducir_a_sql() {

    // 1. Obtener los datos JSON enviados por la interfaz
    $data = json_decode(file_get_contents('php://input'), true);
    
    // 2. Verificar que se haya enviado el campo 'mensaje' con la consulta en lenguaje natural
    if (!isset($data['mensaje']) || empty($data['mensaje'])) {
        echo json_encode(['error' => 'No se proporcionó una consulta en lenguaje natural.']);
        http_response_code(400);  // Bad Request
        return;
    }
    
    // Guardar la consulta natural en una variable
    $consulta_natural = $data['mensaje'];
	error_log("[DEBUG] Consulta natural recibida:\n" . $consulta_natural . "\n");
	
    // 3. Obtener la estructura de la base de datos (campos de cada tabla)
    $estructura_db = obtener_estructura_db();
    if (empty($estructura_db)) {
        echo json_encode(['error' => 'No se pudo obtener la estructura de la base de datos.']);
        http_response_code(500);  // Internal Server Error
        return;
    }
    
    // 4. Construir el prompt para la API, incluyendo el mensaje natural y la estructura.
    // El prompt tiene 5 secciones de instrucciones que deben cumplirse estrictamente:

	//Mandamos la fecha actual a la API
   	$fecha_actual = date('Y-m-d');
	
	$prompt = "Consulta en lenguaje natural: $consulta_natural\n";
	$prompt .= "Fecha actual: " . date('Y-m-d') . "\n";
	$prompt .= "Formato correcto de fechas: YYYY-MM-DD (por ejemplo: " . date('Y-m-d') . ")\n";
	$prompt .= "IMPORTANTE: Las comparaciones de fechas deben usar DATETIME(correos.fecha) para incluir la hora.\n";

	$prompt .= formatear_estructura_base_datos($estructura_db);
	
    $prompt .= "Genera una consulta SQLite válida para la base de datos de correos de tareas, traduciendo la consulta en lenguaje natural, siguiendo estrictamente estas indicaciones:\n";
    $prompt .=
        "1.- En el 'SELECT':\n" .
			"- No uses 'AS'.\n" .
			"- No uses el asterisco para llamar a todos los campos, por ejemplo 'archivos_adjuntos.*' ni tampoco 'correos.*'.\n" .

			"- No uses modificadores como 'DATETIME' 'MAX' 'LOWER' 'COUNT' 'INSTR' 'SUBSTR' 'LENGHT' y demás similares en el SELECT, porque hará que falle la consulta.\n" .
			"- Añade siempre el prefijo de la tabla de todos los campos que uses. Ejemplo: correos.correo_id.\n" .
        
        "2.- Siempre verificar:\n" .
			"- Verifica que los campos que usas existen en la base de datos.\n" .
			"- Para las fechas, usa el formato 'YYYY-MM-DD' y utiliza 'DATE()' o 'DATETIME()' según corresponda.\n" .
			"- Usa 'GROUP BY' para evitar que se devuelvan resultados duplicados.\n" .
        
        "3.- Nunca:\n" .
			"- Nunca incluyas en la consulta el campo 'archivo_binario' de la tabla 'archivos_adjuntos'.\n" .
//			"- Nunca incluyas ni hagas uso del campo 'correos.Asunto'.\n" .

			"- Nunca proceses temporalmente por correo_id. Hazlo siempre por correo.fecha.\n" .
			"- Nunca interpretes 'correo' como 'email' ya que 'correo' o 'correos' se refieren a toda la información del correo o los correos.\n".
			"- Nunca uses en la consulta 'INSTR' 'GLOB'.\n".
        
        "4.- Búsqueda de enlaces, hipervínculos, links, etc.:\n" .
			"- Los enlaces en la base de datos son cadenas separadas por un espacio.\n" .
			"- Cuenta enlaces a partir de los espacios encontrados en el campo 'enlaces'\n." .
			"- Busca enlaces únicamente en el campo 'correos.enlaces'.\n" .
        
        "5.- Búsqueda de correos, mensajes, deberes, etc.:\n".
			"- Si buscas un término en el cuerpo del correo entonces búscalo en el campo 'correos.cuerpo_txt_formateado', y también en el nombre de los archivos adjuntos.\n" .
			
			//Para obtener solo los adjuntos que se requieren para la 2ª consulta de adjuntos
			"- Si consideras que la consulta te pide el o los 'correos completos', entonces incluye todos los campos de la tabla 'correos' salvo 'correos.Asunto'. En caso contrario pon en el SELECT solo los campos que se piden y añade siempre el campo 'correos.correo_id'\n".
			
			//Para que aparezca la fecha en cada adunto en el panel lateral
			"- Si usas algun campo de la tabla 'archivos_adjuntos' añade siempre el campo 'archivos_adjuntos.archivo_id' si no estaba ya, y 'correos.fecha'.\n".
			
			//Para que se detecte bien las palabras a buscar en el procesado y se use REGEXP
			"- Usa siempre LIKE, rodeados de '%' para todos los terminos, nombres, palabras clave, etc. que busques.\n".
			
			//Por los correctores del móvil
			"- Si buscas algun término o nombre propio, al que le falta la tilde según la ortografía española, o que ya lleva tilde, entonces búscala en las dos versiones, tanto corregida con tilde como sin tilde. Siempre en minúsculas y nunca quites ni añadas letras a los nombres.\n".
			"- En el caso de nombres propios de mas de una palabra, deben coincidir las dos.\n".
			
			"-Para filtrar por día completo usa: correos.fecha >= 'YYYY-MM-DD' AND correos.fecha < 'YYYY-MM-DD+1' (rango sin DATETIME).\\n";
			
			


    // 5. Enviar el prompt a la API (por ejemplo a OpenAI) para obtener la consulta SQL
	$respuesta_sql = consultar_api($prompt);
	
    error_log("Depuración: Respuesta original de la API:\n" . $respuesta_sql);
    
    // 6. Postprocesar la consulta SQL para limpiarla y ajustar ciertos detalles.
    // La función procesar_consulta() se encarga de eliminar partes innecesarias, backticks, etc.
    $respuesta_sql = procesar_consulta($respuesta_sql);
	
	//---------------------------------------------------------	
	// FILTRAR POR USUARIO LA CONSULTA PARA MOSTRAR EN EL CHAT
	//---------------------------------------------------------
	
	// Escapar emails manualmente para usarlos en la cláusula IN
	$emails_usuario = array_map(function($email) {
		return "'" . SQLite3::escapeString($email) . "'";
	}, $_SESSION['emails'] ?? []);

	// Aplicar el filtro por usuario
	$respuesta_sql = FILTRAR_POR_USUARIO($respuesta_sql, $emails_usuario);

	error_log("Depuración: Consulta final procesada:\n\n" . $respuesta_sql);

	// 🚀 Devolver al cliente tanto la consulta como la bandera de 'correo_id'
	echo json_encode([
		'sql_query' => $respuesta_sql
	]);
	}



// ==================================================
// Función para obtener la estructura (nombres de columnas) de las tablas de la base de datos
// ==================================================
function obtener_estructura_db() {
    global $ruta_db;  // Se usa la variable global que contiene la ruta de la base de datos SQLite
    $estructura = []; // Arreglo que almacenará la estructura por tabla

    try {
        // Conectar a la base de datos SQLite
		$db = ABRIR_CONEXION_SQLITE($ruta_db);
        
        // Función anónima para ejecutar un PRAGMA que obtiene la información de la tabla
        // Este bloque consulta la información y extrae el nombre de cada columna.
        $ejecutar_pragma = function($sql) use ($db) {
            $resultado = $db->query($sql);
            $columnas = [];
            // Recorre cada fila del resultado del PRAGMA
            while ($fila = $resultado->fetchArray(SQLITE3_ASSOC)) {
                // Si existe el campo "name", lo añade al arreglo de columnas
                if (isset($fila["name"])) {
                    $columnas[] = $fila["name"];
                }
            }
            return $columnas;
        };

        // Se recorre un arreglo con los nombres de las tablas que nos interesan
        foreach (["correos", "archivos_adjuntos"] as $tabla) {
            // Ejecuta PRAGMA table_info para obtener la estructura de cada tabla y la almacena
            $estructura[$tabla] = $ejecutar_pragma("PRAGMA table_info($tabla);");
        }

        // Devuelve el arreglo con la estructura completa de las tablas
        return $estructura;
    } catch (Exception $e) {
        // En caso de error, se registra en el error log y se retorna un arreglo vacío
        error_log("Error al obtener la estructura de la base de datos: " . $e->getMessage());
        return [];
    }
}




// ==================================================
// Función para consultar la API de OpenAI y obtener la respuesta de la IA
// ==================================================
function consultar_api($pregunta) {
    // Asegurarse de que se usen las variables globales con la clave de API y el contexto de sistema
    global $api_key, $context;
	
//    error_log("Depuración: Clave API: " . $api_key);

    // URL de la API de OpenAI para chat completions (modelo GPT-3.5-turbo)
    $url = 'https://api.openai.com/v1/chat/completions';

    // Configurar los encabezados para la solicitud
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ];
	
	// Asegurar que $context tenga siempre un valor válido
	if (!isset($context) || !is_string($context) || trim($context) === '') {
		$context = <<<EOT
	Estás conversando con el desarrollador de un sistema de gestion con IA de una base de datos de correos de clases de musica y guitarra enviados a alumnos.
	Él te pedirá una consulta en lenguaje natural y tú la traducirás a consulta SQLite cumpliendo con las reglas del prompt que se manda. Si no cumples estrictamente con las reglas del prompt estarás haciendo mal tu trabajo.
	EOT;
	}

    // Preparar los datos que se enviarán a la API, incluyendo el contexto (mensaje del sistema)
    // y el mensaje del usuario (la consulta o prompt)
    $data = [
        //'model' => 'gpt-3.5-turbo',
		//'model' => 'gpt-4-1106-preview',
		'model' => 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => $context],
            ['role' => 'user', 'content' => $pregunta]
        ]
    ];

    // Inicializar cURL con la URL de la API
    $ch = curl_init($url);

    // Configurar las opciones de cURL:
    // - Devolver la respuesta como cadena en lugar de imprimirla directamente
    // - Establecer los encabezados HTTP
    // - Indicar que se realizará una solicitud POST, enviando los datos codificados en JSON
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Ejecutar la solicitud cURL y almacenar la respuesta
    $response = curl_exec($ch);
	
    // Obtener el código HTTP de la respuesta para verificar si la llamada fue exitosa
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // En caso de error a nivel de cURL, se puede registrar el error
    if ($response === false) {
        $error_msg = curl_error($ch);
        error_log("Error en cURL: " . $error_msg);
    }
    
    // Cerrar la sesión cURL para liberar recursos
    curl_close($ch);

    // Si la respuesta es exitosa (HTTP 200), decodificar la respuesta y devolver el contenido
    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        // Se asume que la respuesta contiene 'choices' con el mensaje generado por la IA
        return $response_data['choices'][0]['message']['content'];
    } else {
        // Si la API retorna un error, se devuelve un mensaje con el código de estado
        return "Error: " . $http_code;
    }
}



// ==================================================
// Función para limpiar y ajustar la consulta SQL recibida de la API
// ==================================================
function procesar_consulta($respuesta_sql) {
    try {

        // 1. Eliminar todo lo que haya antes de la palabra "SELECT"
        $pos = stripos($respuesta_sql, "SELECT");
        if ($pos !== false) {
            $respuesta_sql = substr($respuesta_sql, $pos);
        }

        // 2. Eliminar cualquier triple backtick que pudiera incluirse en la respuesta
        $respuesta_sql = explode("```", $respuesta_sql)[0];


        // 3. Detectar los campos especificados en el SELECT para asegurar que incluyen ciertos requerimientos
        if (preg_match('/SELECT\s+(.*?)\s+FROM/i', $respuesta_sql, $matches)) {
            // Separamos y limpiamos los campos listados en el SELECT
            $campos_select = array_map('trim', explode(',', $matches[1]));

            // Si se están usando campos de la tabla "archivos_adjuntos"
            // y no se incluyó el campo "correos.correo_id", se añaden campos requeridos.
            if (preg_grep('/^archivos_adjuntos\./', $campos_select) && !in_array("correos.correo_id", $campos_select)) {
                $required = [
                    "archivos_adjuntos.archivo_id",
                    "archivos_adjuntos.correo_id",
                    "archivos_adjuntos.bytes_archivo",
                    "archivos_adjuntos.nombre_archivo"
                ];
                // Agregar cada campo requerido que no exista ya en la lista
                foreach ($required as $campo) {
                    if (!in_array($campo, $campos_select)) {
                        $campos_select[] = $campo;
                    }
                }
                // Reconstruir la parte del SELECT con la lista actualizada de campos
                $nuevo_select = "SELECT " . implode(', ', $campos_select) . " FROM";
                $respuesta_sql = preg_replace('/SELECT\s+(.*?)\s+FROM/i', $nuevo_select, $respuesta_sql, 1);
            }
        }


		// Campos donde se permite aplicar comodines y LIKE flexible
		$campos_like_permitidos = [
			'correos.nombre',
			'correos.cuerpo',
			'correos.cuerpo_txt_formateado',  // ← AÑADIR
			'correos.etiquetas',              // ← AÑADIR
			'correos.enlaces',
			'archivos_adjuntos.nombre_archivo'
		];	


		
		// --------------------------------------------------
		// Reemplazar comparaciones con = o LIKE por REGEXP con límites de palabra
		// Solo para campos permitidos y evitando campos de fecha o formatos de fecha
		// --------------------------------------------------
		// Convertir condiciones con '=' o 'LIKE' en expresiones REGEXP con \b...\b
		$respuesta_sql = preg_replace_callback(
			'/((?:LOWER\([^)]+\))|(?:[a-zA-Z_]+(?:\.[a-zA-Z_]+)?))\s*(=|LIKE)\s*\'(.*?)\'/i',
			function($m) use ($campos_like_permitidos) {
				$columna_original = $m[1];               // Ej: LOWER(correos.nombre) o correos.cuerpo
				$operador = strtoupper($m[2]);           // = o LIKE
				$valor = $m[3];                           // Valor entre comillas

				// Extraer nombre real del campo
				$campo_normalizado = strtolower(preg_replace('/^LOWER\((.*?)\)$/i', '$1', $columna_original));

				// Excepciones: ignorar si es campo de fecha o valor parece fecha
				if ($campo_normalizado === 'correos.fecha') return $m[0];
				if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $valor)) return $m[0];


				// Solo transformar si el campo está permitido
				if (in_array($campo_normalizado, $campos_like_permitidos)) {
					// Limpiar comodines y dividir por palabras
					$valor_limpio = str_replace('%', '', $valor);
					$palabras = preg_split('/\s+/', $valor_limpio, -1, PREG_SPLIT_NO_EMPTY);

					// Construir condiciones REGEXP por palabra
					$condiciones = [];
					foreach ($palabras as $palabra) {
						$palabra_escapada = preg_quote($palabra, '/');
						$condiciones[] = "$campo_normalizado REGEXP '\\\\b($palabra_escapada)\\\\b'";
					}

					return '(' . implode(' AND ', $condiciones) . ')';
				}

				return $m[0]; // Sin cambios si no está permitido
			},
			$respuesta_sql
		);

        // 7. Validar que la consulta no incluya campos prohibidos como "archivo_binario" o "Asunto"
/*        if (stripos($respuesta_sql, 'archivo_binario') !== false || stripos($respuesta_sql, 'Asunto') !== false) {
            throw new Exception("La consulta incluye campos prohibidos ('archivo_binario' o 'Asunto').");
        }
*/

		// 7. Eliminar campos prohibidos: "archivo_binario" y "Asunto"
		if (stripos($respuesta_sql, 'archivo_binario') !== false || stripos($respuesta_sql, 'Asunto') !== false) {
			error_log("[AVISO] La consulta incluye campos prohibidos. Eliminando...");
			
			// Eliminar 'Asunto' del SELECT
			$respuesta_sql = preg_replace('/,\s*correos\.Asunto\b/i', '', $respuesta_sql);
			$respuesta_sql = preg_replace('/correos\.Asunto\s*,\s*/i', '', $respuesta_sql);
			$respuesta_sql = preg_replace('/\bcorreos\.Asunto\b/i', '', $respuesta_sql);
			
			// Eliminar 'archivo_binario' del SELECT
			$respuesta_sql = preg_replace('/,\s*archivos_adjuntos\.archivo_binario\b/i', '', $respuesta_sql);
			$respuesta_sql = preg_replace('/archivos_adjuntos\.archivo_binario\s*,\s*/i', '', $respuesta_sql);
			$respuesta_sql = preg_replace('/\barchivos_adjuntos\.archivo_binario\b/i', '', $respuesta_sql);
			
			error_log("[DEBUG] SQL después de eliminar campos prohibidos: \n" . $respuesta_sql);
		}





        // Devolver la consulta SQL procesada y lista para ejecutarse
        return $respuesta_sql;

    } catch (Exception $e) {
        // En caso de error, registrar el error y devolver la consulta tal como quedó (para depuración)
        error_log("❌ Error procesando la consulta: " . $e->getMessage());
        return $respuesta_sql;
    }
}


// ==================================================
// FUNCION execute_query
// EJECUTA UNA CONSULTA SQL, OBTIENE RESULTADOS Y ADJUNTOS SEGÚN EL CASO,
// LOS AGRUPA POR 'correo_id' Y DEVUELVE EL JSON FINAL AL CLIENTE.
// ==================================================
function execute_query() {
    global $ruta_db;

    try {
        // ─────────────────────────────
        // ▶ LEER Y VALIDAR ENTRADA
        // ─────────────────────────────
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $sql_query = $data['sql_query'] ?? '';
        if (empty($sql_query)) {
            error_log("[ERROR 3] Consulta SQL vacía");
            throw new Exception("Consulta SQL no proporcionada");
        }

        // ─────────────────────────────
        // ▶ VERIFICAR SESIÓN ACTIVA Y EMAILS
        // ─────────────────────────────
        if (!isset($_SESSION['emails']) || !is_array($_SESSION['emails']) || empty($_SESSION['emails'])) {
            error_log("[ERROR] No hay sesión activa o lista de emails vacía");
            echo json_encode(["error" => "Sesión no válida o sin emails asociados"]);
            http_response_code(403);
            return;
        }

        // ─────────────────────────────
        // ▶ CONEXIÓN A BASE DE DATOS
        // ─────────────────────────────
        $db = ABRIR_CONEXION_SQLITE($ruta_db);

        // ─────────────────────────────
        // 🧩 Obtener emails sanitizados desde sesión
        // ─────────────────────────────
        $emails_usuario = OBTENER_EMAILS_SESION_ESCAPADOS($db);


        // ─────────────────────────────
        // ▶ EJECUTAR CONSULTA PRINCIPAL
        // ─────────────────────────────
        // ─────────────────────────────
        // 🧩 Crear tabla temporal de emails del usuario si la SQL la usa
        // ─────────────────────────────
        if (stripos($sql_query, 'correos_permitidos') !== false) {
            CREAR_TABLA_PERMITIDOS_CONEXION($db);
        }
        error_log("[DEBUG 6] Ejecutando consulta principal...");
        $result = $db->query($sql_query);
        if (!$result) {
            $error = $db->lastErrorMsg();
            error_log("[ERROR 7] Error SQL: " . $error);
            throw new Exception($error);
        }

        // ─────────────────────────────
        // ▶ PROCESAR RESULTADOS
        // ─────────────────────────────
        $formatted_results = [];
        $row_count = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row_count++;
            $formatted_results[] = $row;
        }
        error_log("[DEBUG 9] Total filas obtenidas: $row_count");

        if ($row_count === 0) {
            error_log("[DEBUG 10] Sin resultados, retornando vacío");
            echo json_encode(["results" => []]);
            return;
        }

        // ─────────────────────────────
        // ▶ 2ª CONSULTA DE ADJUNTOS
        // ─────────────────────────────
        // Si la SQL incluye 'archivo_id' → obtener adjuntos específicos
        // Si no, pero se piden todos los campos (se entiende correo COMPLETO) → obtener todos los adjuntos por correo_id
        // Si no se cumple ninguna → no se realiza la 2ª consulta


        // ▶ COMPROBAR CAMPOS DE 'correos'
        $todos_los_campos_correos = false;
		$campos_requeridos = [
			"correos.correo_id",
			"correos.nombre",
			"correos.email",
			"correos.fecha",
			"correos.cuerpo",
			"correos.cuerpo_txt_formateado",  // ← AÑADIR
			"correos.etiquetas",              // ← AÑADIR
			"correos.canciones",              // ← CAMPO NUEVO: se conserva para reconocer un correo completo
			"correos.enlaces",
			"correos.numero_archivos_adjuntos"
		];




        if (preg_match('/SELECT\s+(.*?)\s+FROM/is', $sql_query, $matches)) {
            $campos_en_select = explode(',', $matches[1]);
            $campos_en_select = array_map(function($campo) {
                return strtolower(trim($campo));
            }, $campos_en_select);

            error_log("[DEBUG] Campos encontrados en SELECT: " . json_encode($campos_en_select));

            $todos_presentes = true;
            foreach ($campos_requeridos as $campo) {
                if (!in_array(strtolower($campo), $campos_en_select)) {
                    $todos_presentes = false;
                    error_log("[DEBUG] Campo faltante: '$campo'");
                }
            }

            $todos_los_campos_correos = $todos_presentes;
            error_log("[DEBUG] Resultado: todos_los_campos_correos = " . ($todos_los_campos_correos ? 'true' : 'false'));
        }

        // ▶ COMPROBAR SI INCLUYE 'archivo_id'
        $tiene_archivo_id = false;
        foreach ($formatted_results as $r) {
            if (isset($r['archivo_id']) || isset($r['archivos_adjuntos.archivo_id'])) {
                $tiene_archivo_id = true;
                break;
            }
        }

        // ▶ OBTENER ADJUNTOS SEGÚN CASO
        $adjuntos_por_correo = [];

        if ($tiene_archivo_id) {
            $archivo_ids = array_values(array_unique(array_filter(array_map(function($r) {
                return $r['archivo_id'] ?? $r['archivos_adjuntos.archivo_id'] ?? null;
            }, $formatted_results))));
            error_log("[DEBUG] IDs de archivo_id: " . json_encode($archivo_ids));

            $adjuntos_por_correo = OBTENER_ADJUNTOS_POR_ARCHIVO_ID($db, $archivo_ids);

        } elseif ($todos_los_campos_correos) {
            $correo_ids = array_values(array_unique(array_filter(array_map(function($r) {
                return $r['correo_id'] ?? $r['correos.correo_id'] ?? null;
            }, $formatted_results))));
            error_log("[DEBUG] IDs de correo_id: " . json_encode($correo_ids));

            $adjuntos_por_correo = OBTENER_ADJUNTOS_POR_CORREO_ID($db, $correo_ids);

        } else {
            error_log("[DEBUG] No se realiza la 2ª consulta de adjuntos.");
        }

        // ─────────────────────────────
        // ▶ LIMPIAR CAMPOS
        // ─────────────────────────────
        $cleaned_results = LIMPIAR_CAMPOS_RESULTADOS($formatted_results);

        // ─────────────────────────────
        // ▶ AGRUPAR POR 'correo_id' Y FUSIONAR ADJUNTOS
        // ─────────────────────────────
        $grouped = AGRUPAR_POR_CORREO_ID($cleaned_results, $adjuntos_por_correo);

        // ─────────────────────────────
        // ▶ PREPARAR Y ENVIAR RESPUESTA
        // ─────────────────────────────
        $final_results = array_values($grouped);
        $response_json = json_encode(
            ['results' => $final_results],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        error_log("[DEBUG] JSON enviado al cliente:\n" . $response_json);
        echo $response_json;
        exit;

    } catch (Exception $e) {
        // ─────────────────────────────
        // ▶ MANEJO DE ERRORES
        // ─────────────────────────────
        error_log("[ERROR 23] " . $e->getMessage());
        echo json_encode(["error" => $e->getMessage()]);
        http_response_code(500);
    } finally {
        // ─────────────────────────────
        // ▶ LIMPIEZA FINAL
        // ─────────────────────────────
        error_log("[DEBUG 24] Limpieza final");
        if (isset($db)) {
            $db->close();
        }
    }
}




// ==================================================
// OBTENER_ADJUNTOS_POR_ARCHIVO_ID
// CONSULTA ADJUNTOS USANDO UNA LISTA DE ARCHIVO_ID Y DEVUELVE ARRAY AGRUPADO POR CORREO_ID.
// ==================================================
function OBTENER_ADJUNTOS_POR_ARCHIVO_ID($db, $archivo_ids) {
    $adjuntos_por_correo = [];

    if (!empty($archivo_ids)) {
        $placeholders = implode(',', array_fill(0, count($archivo_ids), '?'));
        $sql_adjuntos = "SELECT archivo_id, correo_id, nombre_archivo 
                        FROM archivos_adjuntos 
                        WHERE archivo_id IN ($placeholders)";
        error_log("[DEBUG] Consulta adjuntos por archivo_id:\n" . $sql_adjuntos);

        $stmt = $db->prepare($sql_adjuntos);
        foreach ($archivo_ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, SQLITE3_INTEGER);
        }
        $result_adjuntos = $stmt->execute();

        while ($adjunto = $result_adjuntos->fetchArray(SQLITE3_ASSOC)) {
            $cid = $adjunto['correo_id'];
            if (!isset($adjuntos_por_correo[$cid])) {
                $adjuntos_por_correo[$cid] = [];
            }
            $adjuntos_por_correo[$cid][] = $adjunto;
        }
    }

    return $adjuntos_por_correo;
}


// ==================================================
// OBTENER_ADJUNTOS_POR_CORREO_ID
// CONSULTA ADJUNTOS USANDO UNA LISTA DE CORREO_ID Y DEVUELVE ARRAY AGRUPADO POR CORREO_ID.
// ==================================================
function OBTENER_ADJUNTOS_POR_CORREO_ID($db, $correo_ids) {
    $adjuntos_por_correo = [];

    if (!empty($correo_ids)) {
        $placeholders = implode(',', array_fill(0, count($correo_ids), '?'));
        $sql_adjuntos = "SELECT archivo_id, correo_id, nombre_archivo 
                        FROM archivos_adjuntos 
                        WHERE correo_id IN ($placeholders)";
        error_log("[DEBUG] Consulta adjuntos por correo_id:\n" . $sql_adjuntos);

        $stmt = $db->prepare($sql_adjuntos);
        foreach ($correo_ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, SQLITE3_INTEGER);
        }
        $result_adjuntos = $stmt->execute();

        while ($adjunto = $result_adjuntos->fetchArray(SQLITE3_ASSOC)) {
            $cid = $adjunto['correo_id'];
            if (!isset($adjuntos_por_correo[$cid])) {
                $adjuntos_por_correo[$cid] = [];
            }
            $adjuntos_por_correo[$cid][] = $adjunto;
        }
    }

    return $adjuntos_por_correo;
}

// ==================================================
// AGRUPAR_POR_CORREO_ID
// AGRUPA RESULTADOS POR CORREO_ID Y FUSIONA LOS ADJUNTOS CORRESPONDIENTES.
// ==================================================
function AGRUPAR_POR_CORREO_ID($cleaned_results, $adjuntos_por_correo) {
    $grouped = [];

    foreach ($cleaned_results as $item) {
        $cid = $item['correo_id'] ?? null;

        if (!$cid) {
            error_log("[DEBUG 16] Item sin correo_id: " . json_encode($item));
            continue;
        }

        if (isset($grouped[$cid])) {
            $existing_attachments = $grouped[$cid]['attachments'] ?? [];
            $new_attachments = $adjuntos_por_correo[$cid] ?? [];
            $merged_attachments = array_merge($existing_attachments, $new_attachments);
            $grouped[$cid]['attachments'] = $merged_attachments;
        } else {
            $item['attachments'] = $adjuntos_por_correo[$cid] ?? [];
            $grouped[$cid] = $item;
        }
    }

    // ELIMINAR DUPLICADOS EN ADJUNTOS
    foreach ($grouped as &$grupo) {
        if (!empty($grupo['attachments'])) {
            $unique = [];
            $seen_ids = [];
            foreach ($grupo['attachments'] as $att) {
                if (!in_array($att['archivo_id'], $seen_ids)) {
                    $unique[] = $att;
                    $seen_ids[] = $att['archivo_id'];
                }
            }
            $grupo['attachments'] = $unique;
            error_log("[DEBUG 20] CID {$grupo['correo_id']}: Adjuntos únicos: " . count($unique));
        }
    }

    return array_values($grouped);
}

// ==================================================
// Función para aplicar filtrado por usuario usando la tabla temporal
// ==================================================
function FILTRAR_POR_USUARIO($sql_query) {
    // Acceder a la ruta de la base de datos
    global $ruta_db;

    // Si el usuario es profesor, no se filtra nada
    if ($_SESSION['acceso_seccion'] === 'profesor') {
        error_log("🟡 Rol profesor: no se aplica filtro SQL.");
        return $sql_query;
    }

    // Verificar que hay emails válidos en la sesión
    if (!isset($_SESSION['emails']) || empty($_SESSION['emails'])) {
        error_log("❌ No hay sesión activa o emails para el filtrado.");
        throw new Exception("Sesión inválida o incompleta.");
    }

    // Abrir conexión a la base de datos
    $db = new SQLite3($ruta_db);

    // Crear tabla temporal en esta conexión
    $count = CREAR_TABLA_PERMITIDOS_CONEXION($db);

    // Si no se pudo crear correctamente, abortar
    if ($count === 0) {
        $db->close();
        throw new Exception("No se pudo crear la tabla de permisos.");
    }

    // Si la consulta trabaja sobre la tabla 'correos'
    if (preg_match('/FROM\s+correos\b/i', $sql_query)) {
        // Reemplazar con JOIN a la tabla temporal
        $sql_query = preg_replace(
            '/FROM\s+correos\b/i',
            'FROM correos_permitidos JOIN correos USING (correo_id)',
            $sql_query
        );
    }

    // Si trabaja sobre la tabla 'archivos_adjuntos'
    elseif (preg_match('/FROM\s+archivos_adjuntos\b/i', $sql_query)) {
        // Preparar el JOIN necesario con la tabla temporal
        $join = "JOIN correos_permitidos ON archivos_adjuntos.correo_id = correos_permitidos.correo_id";

        // Insertar el JOIN después de otros posibles JOIN existentes
        if (preg_match('/(FROM\s+archivos_adjuntos\b[^\n]*?)(\s+JOIN\b)/i', $sql_query)) {
            $sql_query = preg_replace(
                '/(FROM\s+archivos_adjuntos\b[^\n]*?)(\s+JOIN\b)/i',
                "$1 $join$2",
                $sql_query
            );
        } else {
            // Si no hay JOINs previos, insertar directamente
            $sql_query = preg_replace(
                '/FROM\s+archivos_adjuntos\b/i',
                "FROM archivos_adjuntos $join",
                $sql_query
            );
        }
    }

    // Cerrar conexión SQLite
    $db->close();

    // Devolver la consulta filtrada
    return $sql_query;
}









function obtener_metadatos() {
    // 1. Leer y decodificar el cuerpo JSON recibido por POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $url = $data['url'] ?? '';

    // 2. Validar que la URL sea válida
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'No se proporcionó una URL válida']);
        http_response_code(400);
        return;
    }

    // 3. Si la URL es de YouTube → extraer el ID y usar la API oficial
    if (preg_match('/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $videoId = $m[1];
        
		//Tu clave real de la YouTube Data API v3
        global $env;
		$api_key = $env["YOUTUBE_KEY"];

		
		$metadatos = obtener_metadatos_youtube($videoId, $api_key);

        // Si se obtuvieron metadatos, devolverlos y salir
        if ($metadatos) {
            echo json_encode($metadatos);
            return;
        }
    }

    // 4. Para el resto de URLs → descargar el HTML usando cURL con cabeceras de navegador
    $html = descargar_html_con_curl($url);
    if ($html === false) {
        echo json_encode(['error' => 'No se pudo acceder a la URL']);
        http_response_code(500);
        return;
    }

    // 5. Cargar el HTML en DOMDocument y preparar XPath
    libxml_use_internal_errors(true); // Evita warnings por HTML mal formado
    $dom = new DOMDocument();
    @$dom->loadHTML($html); // Cargar el HTML recibido
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // 6. Función interna para obtener metadatos tipo Open Graph (og:title, og:image, etc.)
    $getMeta = function($prop) use ($xpath) {
        $nodo = $xpath->query("//meta[@property='og:$prop']")->item(0);
        return $nodo ? html_entity_decode($nodo->getAttribute('content')) : null;
    };

    // 7. Intentar obtener los metadatos OG
    $ogTitle = $getMeta('title');
    $ogDesc  = $getMeta('description');
    $ogImage = $getMeta('image');
    $ogUrl   = $getMeta('url');

    // 8. Fallback: si no hay og:title → usar <title>
    if (!$ogTitle) {
        $titulo = $xpath->query("//title")->item(0);
        if ($titulo) $ogTitle = html_entity_decode(trim($titulo->textContent));
    }

    // 9. Fallback: si no hay og:description → usar meta name="description"
    if (!$ogDesc) {
        $desc = $xpath->query("//meta[@name='description']")->item(0);
        if ($desc) $ogDesc = html_entity_decode($desc->getAttribute('content'));
    }

    // 10. Construir y devolver los metadatos
    echo json_encode([
        'title' => $ogTitle ?: 'Sin título',
        'description' => $ogDesc ?: 'Sin descripción',
        'image' => $ogImage,
        'url' => $ogUrl ?: $url
    ]);
}



// ==================================================
// Consulta la API oficial de YouTube Data v3 para obtener metadatos reales
// de un vídeo a partir de su ID. Devuelve título, descripción, imagen y URL.
//
// @param string $videoId  ID del vídeo de YouTube (11 caracteres)
// @param string $api_key  Clave API de tu proyecto de Google Cloud
// @return array|null      Array con metadatos o null si falla
// ==================================================
function obtener_metadatos_youtube($videoId, $api_key) {
    // Construir la URL de la API con el ID del vídeo y tu clave API
    $api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet&id={$videoId}&key={$api_key}";

    // Llamar a la API usando file_get_contents
    $response = file_get_contents($api_url);
    if ($response === false) {
        error_log("❌ No se pudo acceder a la API de YouTube");
        return null;
    }

    // Decodificar el JSON recibido
    $data = json_decode($response, true);

    // Verificar que haya datos disponibles
    if (!isset($data['items'][0]['snippet'])) {
        error_log("⚠️ No se encontraron datos para el video ID: $videoId");
        return null;
    }

    $snippet = $data['items'][0]['snippet'];

    // Devolver los metadatos en formato estándar
    return [
        'title' => $snippet['title'] ?? 'Sin título',
		'description' => isset($snippet['description']) ? mb_strimwidth($snippet['description'], 0, 300, '...') : 'Sin descripción',
        'image' => $snippet['thumbnails']['high']['url'] ?? null,
        'url' => "https://www.youtube.com/watch?v=$videoId"
    ];
}


// ==================================================
// * Descarga el HTML de una URL simulando un navegador real usando cURL.
// * Devuelve el HTML en formato HTML-ENTITIES para DOMDocument.
// * Si hay error (403, 404, etc.), devuelve false y genera JSON válido de error.
// ==================================================
function descargar_html_con_curl($url) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,       // Devolver el contenido como string
        CURLOPT_FOLLOWLOCATION => true,       // Seguir redirecciones 3xx
        CURLOPT_ENCODING => '',               // Aceptar gzip, deflate, br
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                           . '(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9',
            'Referer: https://www.google.com/',
        ],
        CURLOPT_TIMEOUT => 15
    ]);

    $html = curl_exec($ch);
    $error = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Si la respuesta falla o no es HTTP 200, devolver JSON de error
    if ($html === false || $code !== 200) {
        error_log("❌ Error cURL: $error | Código HTTP: $code");
        echo json_encode(['error' => "Error al acceder a la URL (código $code)"]);
        http_response_code(500);
        return false;
    }

    // Forzar codificación a HTML válido
    return mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
}



// ==================================================
// LIMPIAR_CAMPOS_RESULTADOS
// ELIMINA PREFIJOS DE LOS KEYS EN LOS RESULTADOS SQL.
// ==================================================
function LIMPIAR_CAMPOS_RESULTADOS($formatted_results) {
    $cleaned_results = [];
    foreach ($formatted_results as $result) {
        $cleaned = [];
        foreach ($result as $key => $value) {
            $new_key = strpos($key, '.') !== false ? substr($key, strrpos($key, '.') + 1) : $key;
            $cleaned[$new_key] = $value;
        }
        $cleaned_results[] = $cleaned;
    }
    return $cleaned_results;
}
