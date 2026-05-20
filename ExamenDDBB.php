<?php

/**
 * @file ExamenDDBB.php
 * @brief Capa de acceso a datos para la gestión de exámenes online.
 *
 * Contiene todas las operaciones de base de datos relacionadas con exámenes,
 * preguntas, respuestas y resultados de alumnos.
 *
 * @author Javier Castro Muriana
 * @version 1.0
 * @date 202
 */

require_once "ConexionDB.php";

/**
 * @class ExamenDDBB
 * @brief Gestiona el acceso a la base de datos del sistema de exámenes online.
 *
 * Esta clase actúa como repositorio centralizado para todas las consultas
 * relacionadas con materias, grupos, exámenes, preguntas y respuestas.
 * Utiliza PDO para la comunicación con la base de datos.
 *
 * @example
 * ```php
 * $db = new ExamenDDBB();
 * $materias = $db->getMaterias();
 * ```
 */
class ExamenDDBB {

    /**
     * @var PDO $conexion
     * @brief Instancia de conexión PDO a la base de datos.
     */
    private $conexion;

    /**
     * @brief Constructor de la clase. Inicializa la conexión a la base de datos.
     *
     * Obtiene una instancia de conexión PDO a la base de datos "examenes_online"
     * mediante el patrón Singleton implementado en ConexionDB.
     */
    public function __construct() {
        $this->conexion = ConexionDB::getConexion("examenes_online");
    }

    /* ======================
       LISTADOS
    ====================== */

    /**
     * @brief Obtiene todas las materias disponibles en el sistema.
     *
     * @return array Array asociativo con todas las filas de la tabla materias.
     */
    public function getMaterias() {
        return $this->conexion->query("SELECT * FROM materias")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Obtiene todos los grupos registrados en el sistema.
     *
     * @return array Array asociativo con todas las filas de la tabla grupos.
     */
    public function getGrupos() {
        return $this->conexion->query("SELECT * FROM grupos")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Obtiene todas las categorías del banco de preguntas.
     *
     * @return array Array asociativo con todas las filas de la tabla categorias.
     */
    public function getCategorias() {
        return $this->conexion->query("SELECT * FROM categorias")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ======================
       EXÁMENES
    ====================== */

    /**
     * @brief Crea un nuevo examen en la base de datos.
     *
     * Inserta un examen con estado inicial 'abierto' y devuelve el ID
     * generado automáticamente por la base de datos.
     *
     * @param string $nombre     Nombre descriptivo del examen.
     * @param int    $id_grupo   Identificador del grupo al que va dirigido.
     * @param int    $id_materia Identificador de la materia del examen.
     * @param string $inicio     Fecha y hora de inicio (formato 'YYYY-MM-DD HH:MM:SS').
     * @param string $fin        Fecha y hora de fin (formato 'YYYY-MM-DD HH:MM:SS').
     *
     * @return string ID del examen recién creado.
     */
    public function crearExamen($nombre, $id_grupo, $id_materia, $inicio, $fin) {
        $sql = "INSERT INTO examenes 
                (nombre, id_grupo, id_materia, fecha_inicio, fecha_fin, estado)
                VALUES (?, ?, ?, ?, ?, 'abierto')";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$nombre, $id_grupo, $id_materia, $inicio, $fin]);

        return $this->conexion->lastInsertId();
    }

    /* ======================
       PREGUNTAS
    *******************************NUEVO COMENTARIO*********************
    ====================== */

    /**
     * @brief Obtiene todas las preguntas del banco de preguntas.
     *
     * @return array Array asociativo con todas las preguntas almacenadas.
     */
    public function getBancoPreguntas() {
        $sql = "SELECT * FROM banco_preguntas";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Obtiene un número aleatorio de preguntas de una categoría específica.
     *
     * Selecciona aleatoriamente un conjunto de IDs de preguntas pertenecientes
     * a la categoría indicada.
     *
     * @param int $idCategoria Identificador de la categoría.
     * @param int $limite      Número máximo de preguntas a devolver.
     *                         Se convierte a entero para evitar inyección SQL en LIMIT.
     *
     * @return array Array con los IDs de las preguntas seleccionadas aleatoriamente.
     */
    public function getPreguntasPorCategoria($idCategoria, $limite) {
        $con = ConexionDB::getConexion("examenes_online");

        $limite = (int)$limite; // MUY IMPORTANTE: evita inyección SQL en cláusula LIMIT

        $sql = "SELECT id_pregunta 
            FROM banco_preguntas 
            WHERE id_categoria = ?
            ORDER BY RAND()
            LIMIT $limite";

        $stmt = $con->prepare($sql);
        $stmt->execute([$idCategoria]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Inserta una pregunta en un examen concreto.
     *
     * @param int $idExamen   Identificador del examen.
     * @param int $idPregunta Identificador de la pregunta del banco.
     *
     * @return bool true si la inserción fue correcta, false en caso contrario.
     */
    public function insertarPreguntaExamen($idExamen, $idPregunta) {
        $sql = "INSERT INTO examen_preguntas (id_examen, id_pregunta)
                VALUES (?, ?)";
        $stmt = $this->conexion->prepare($sql);
        return $stmt->execute([$idExamen, $idPregunta]);
    }

    /**
     * @brief Obtiene el listado de exámenes con información del grupo y materia.
     *
     * Pensado para la vista del profesor, muestra todos los exámenes
     * junto con el nombre del grupo y la materia asociados.
     *
     * @return array Array asociativo con los exámenes y sus datos relacionados.
     */
    public function getExamenesProfesor() {
        $sql = "SELECT e.id_examen, e.nombre, e.fecha_inicio, e.fecha_fin,
                   g.nombre AS grupo, m.nombre AS materia
            FROM examenes e
            JOIN grupos g ON e.id_grupo = g.id_grupo
            JOIN materias m ON e.id_materia = m.id_materia";

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Obtiene los enunciados de las preguntas de un examen.
     *
     * @param int $idExamen Identificador del examen.
     *
     * @return array Array con los enunciados de las preguntas del examen.
     */
    public function getPreguntasExamen($idExamen) {
        $sql = "SELECT p.enunciado
            FROM examen_preguntas ep
            JOIN banco_preguntas p ON ep.id_pregunta = p.id_pregunta
            WHERE ep.id_examen = ?";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idExamen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Obtiene todos los datos de las preguntas de un examen.
     *
     * A diferencia de getPreguntasExamen(), devuelve el registro completo
     * de cada pregunta (todos los campos de banco_preguntas).
     *
     * @param int $idExamen Identificador del examen.
     *
     * @return array Array asociativo con los datos completos de cada pregunta.
     */
    public function getPreguntasExamenDetalladas($idExamen) {
        $sql = "SELECT p.*
            FROM banco_preguntas p
            JOIN examen_preguntas ep ON p.id_pregunta = ep.id_pregunta
            WHERE ep.id_examen = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idExamen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Obtiene las respuestas posibles de una pregunta.
     *
     * Devuelve el ID, texto y si es correcta para cada opción de respuesta.
     *
     * @param int $idPregunta Identificador de la pregunta.
     *
     * @return array Array asociativo con id_respuesta, texto y es_correcta.
     */
    public function getRespuestasPregunta($idPregunta) {
        $sql = "SELECT id_respuesta, texto, es_correcta
            FROM respuestas_posibles
            WHERE id_pregunta = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idPregunta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Asigna una materia a un examen existente.
     *
     * @param int $idExamen  Identificador del examen a modificar.
     * @param int $idMateria Identificador de la nueva materia.
     *
     * @return void
     */
    public function asignarExamenAMateria($idExamen, $idMateria) {
        $sql = "UPDATE examenes SET id_materia = ? WHERE id_examen = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idMateria, $idExamen]);
    }

    /**
     * @brief Obtiene los exámenes disponibles para un alumno según su grupo.
     *
     * Devuelve todos los exámenes cuyo grupo coincide con alguno de los grupos
     * a los que pertenece el alumno.
     *
     * @param int $idUsuario Identificador del usuario (alumno).
     *
     * @return array Array asociativo con los exámenes disponibles para el alumno.
     */
    public function getExamenesAlumno($idUsuario) {
        $sql = "SELECT e.*
            FROM examenes e
            JOIN alumnos_grupos ag ON e.id_grupo = ag.id_grupo
            WHERE ag.idUsuario = ?";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @brief Guarda la respuesta de tipo radio/selección de un alumno.
     *
     * Almacena el ID de la respuesta seleccionada y la calificación obtenida (0 o 1).
     *
     * @param int $idExamen     Identificador del examen.
     * @param int $idPregunta   Identificador de la pregunta.
     * @param int $idUsuario    Identificador del alumno.
     * @param int $idRespuesta  ID de la respuesta seleccionada.
     * @param int $calificacion Puntuación obtenida (1 si correcta, 0 si incorrecta).
     *
     * @return void
     */
    public function guardarRespuestaAlumno($idExamen, $idPregunta, $idUsuario, $idRespuesta, $calificacion) {
        $sql = "INSERT INTO respuestas_alumnos 
            (id_examen, id_pregunta, idUsuario, respuesta_texto, calificacion)
            VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idExamen, $idPregunta, $idUsuario, $idRespuesta, $calificacion]);
    }

    /**
     * @brief Guarda la respuesta de texto libre de un alumno.
     *
     * Almacena el texto introducido por el alumno y la calificación obtenida.
     *
     * @param int    $idExamen     Identificador del examen.
     * @param int    $idPregunta   Identificador de la pregunta.
     * @param int    $idUsuario    Identificador del alumno.
     * @param string $texto        Texto de la respuesta introducida por el alumno.
     * @param int    $calificacion Puntuación obtenida (1 si correcta, 0 si incorrecta).
     *
     * @return void
     */
    public function guardarRespuestaAlumnoTexto($idExamen, $idPregunta, $idUsuario, $texto, $calificacion) {
        $sql = "INSERT INTO respuestas_alumnos 
            (id_examen, id_pregunta, idUsuario, respuesta_texto, calificacion)
            VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idExamen, $idPregunta, $idUsuario, $texto, $calificacion]);
    }

    /**
     * @brief Comprueba si una respuesta de tipo radio es correcta.
     *
     * @param int $idRespuesta Identificador de la respuesta a comprobar.
     *
     * @return bool true si la respuesta es correcta, false en caso contrario.
     */
    public function esRespuestaCorrecta($idRespuesta) {
        $sql = "SELECT es_correcta 
            FROM respuestas_posibles 
            WHERE id_respuesta = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idRespuesta]);
        return $stmt->fetchColumn() == 1;
    }

    /**
     * @brief Comprueba si la respuesta de texto de un alumno es correcta.
     *
     * Realiza dos tipos de comparación frente a todas las respuestas correctas
     * almacenadas para esa pregunta:
     * - Comparación exacta (insensible a mayúsculas y espacios).
     * - Comparación numérica (por si el valor es un número con diferente formato).
     *
     * @param int    $idPregunta  Identificador de la pregunta.
     * @param string $textoAlumno Texto introducido por el alumno.
     *
     * @return bool true si el texto coincide con alguna respuesta correcta, false en caso contrario.
     */
    public function esTextoCorrecto($idPregunta, $textoAlumno) {
        $sql = "SELECT texto 
            FROM respuestas_posibles
            WHERE id_pregunta = ? AND es_correcta = 1";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idPregunta]);
        $correctas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $textoAlumno = strtolower(trim($textoAlumno));

        foreach ($correctas as $textoCorrecto) {
            $textoCorrecto = strtolower(trim($textoCorrecto));

            if ($textoCorrecto === $textoAlumno) {
                return true;
            }

            if (is_numeric($textoCorrecto) && is_numeric($textoAlumno)) {
                if ((float)$textoCorrecto == (float)$textoAlumno) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @brief Comprueba si un alumno ya ha realizado un examen.
     *
     * @param int $idExamen  Identificador del examen.
     * @param int $idUsuario Identificador del alumno.
     *
     * @return bool true si el alumno ya entregó el examen, false si no lo ha hecho.
     */
    public function examenYaRealizado($idExamen, $idUsuario) {
        $sql = "SELECT COUNT(*) 
            FROM respuestas_alumnos 
            WHERE id_examen = ? AND idUsuario = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idExamen, $idUsuario]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * @brief Calcula el resultado de un alumno en un examen.
     *
     * Devuelve el número de aciertos, el total de preguntas respondidas y
     * el porcentaje de aciertos redondeado a dos decimales.
     *
     * @param int $idExamen  Identificador del examen.
     * @param int $idUsuario Identificador del alumno.
     *
     * @return array|null Array con claves 'aciertos', 'total' y 'porcentaje',
     *                    o null si el alumno no tiene respuestas registradas.
     */
    public function getResultadoExamenAlumno($idExamen, $idUsuario) {
        $sql = "SELECT 
                COUNT(*) AS total,
                SUM(calificacion) AS aciertos
            FROM respuestas_alumnos
            WHERE id_examen = ? AND idUsuario = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([$idExamen, $idUsuario]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res["total"] == 0) return null;

        $porcentaje = round(($res["aciertos"] / $res["total"]) * 100, 2);

        return [
            "aciertos"   => $res["aciertos"],
            "total"      => $res["total"],
            "porcentaje" => $porcentaje
        ];
    }

    /**
     * @brief Obtiene la revisión detallada de un examen realizado por un alumno.
     *
     * Para cada pregunta respondida devuelve: el enunciado, el tipo de pregunta,
     * la calificación obtenida, la respuesta del alumno (texto o radio) y
     * la respuesta o respuestas correctas concatenadas.
     *
     * @param int $idExamen  Identificador del examen.
     * @param int $idUsuario Identificador del alumno.
     *
     * @return array Array asociativo con una fila por pregunta respondida,
     *               incluyendo enunciado, tipo, calificacion, respuesta_texto,
     *               respuesta_radio y respuesta_correcta.
     */
    public function getRevisionExamen($idExamen, $idUsuario) {
        $sql = "SELECT 
                p.enunciado,
                p.tipo,
                ra.calificacion,
                ra.respuesta_texto,
                rpos.texto AS respuesta_radio,
                (
                    SELECT GROUP_CONCAT(texto SEPARATOR ', ')
                    FROM respuestas_posibles
                    WHERE id_pregunta = p.id_pregunta
                    AND es_correcta = 1
                ) AS respuesta_correcta
            FROM respuestas_alumnos ra
            JOIN banco_preguntas p 
                ON ra.id_pregunta = p.id_pregunta
            LEFT JOIN respuestas_posibles rpos 
                ON ra.respuesta_texto = rpos.id_respuesta
            WHERE ra.id_examen = :idExamen
            AND ra.idUsuario = :idUsuario";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ":idExamen"  => $idExamen,
            ":idUsuario" => $idUsuario
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}