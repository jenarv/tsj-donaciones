-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 13, 2026 at 01:33 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tsj_donaciones`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `crear_admin_departamento` (IN `p_nombre_completo` VARCHAR(150), IN `p_email` VARCHAR(100), IN `p_password_hash` VARCHAR(255), IN `p_codigo_departamento` VARCHAR(20), OUT `p_id_usuario` INT, OUT `p_mensaje` VARCHAR(255))   BEGIN
    DECLARE v_id_departamento INT;
    DECLARE v_email_existe INT;
    
    -- Check if email exists
    SELECT COUNT(*) INTO v_email_existe
    FROM usuarios_admin
    WHERE email = p_email;
    
    IF v_email_existe > 0 THEN
        SET p_id_usuario = NULL;
        SET p_mensaje = 'El email ya está registrado';
    ELSE
        -- Get department ID
        SELECT id_departamento INTO v_id_departamento
        FROM departamentos
        WHERE codigo_departamento = p_codigo_departamento AND activo = 1;
        
        IF v_id_departamento IS NULL THEN
            SET p_id_usuario = NULL;
            SET p_mensaje = 'Departamento no encontrado';
        ELSE
            -- Insert new admin
            INSERT INTO usuarios_admin (
                nombre_completo, 
                email, 
                password_hash, 
                rol, 
                rol_tipo, 
                id_departamento
            ) VALUES (
                p_nombre_completo,
                p_email,
                p_password_hash,
                'Admin',
                'Admin_Departamento',
                v_id_departamento
            );
            
            SET p_id_usuario = LAST_INSERT_ID();
            SET p_mensaje = 'Admin de departamento creado exitosamente';
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `obtener_articulos_para_admin` (IN `p_id_usuario` INT)   BEGIN
    DECLARE v_rol_tipo VARCHAR(50);
    DECLARE v_id_departamento INT;
    
    -- Get admin info
    SELECT rol_tipo, id_departamento 
    INTO v_rol_tipo, v_id_departamento
    FROM usuarios_admin
    WHERE id_usuario = p_id_usuario AND activo = 1;
    
    -- Return items based on role
    IF v_rol_tipo = 'Super_Admin' THEN
        -- Super Admin sees everything
        SELECT ca.*, cat.nombre_categoria, cat.tipo_acceso
        FROM catalogo_articulos ca
        INNER JOIN categorias cat ON ca.id_categoria = cat.id_categoria
        WHERE cat.activo = 1
        ORDER BY ca.nombre;
    ELSE
        -- Department Admin sees only their department + universal
        SELECT ca.*, cat.nombre_categoria, cat.tipo_acceso
        FROM catalogo_articulos ca
        INNER JOIN categorias cat ON ca.id_categoria = cat.id_categoria
        WHERE cat.activo = 1
            AND (
                cat.tipo_acceso = 'Universal'
                OR cat.id_departamento = v_id_departamento
            )
        ORDER BY ca.nombre;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `obtener_articulos_para_estudiante` (IN `p_id_estudiante` INT)   BEGIN
    SELECT 
        ad.*
    FROM articulos_disponibles ad
    INNER JOIN categorias c ON ad.id_categoria = c.id_categoria
    INNER JOIN estudiantes e ON e.id_estudiante = p_id_estudiante
    WHERE (
        c.tipo_acceso = 'Universal'
        OR c.id_departamento = e.id_departamento
    )
    ORDER BY ad.nombre;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `obtener_estadisticas` (IN `p_id_usuario` INT)   BEGIN
    DECLARE v_rol_tipo VARCHAR(50);
    DECLARE v_id_departamento INT;
    
    -- Get user role
    SELECT rol_tipo, id_departamento 
    INTO v_rol_tipo, v_id_departamento
    FROM usuarios_admin
    WHERE id_usuario = p_id_usuario AND activo = 1;
    
    IF v_rol_tipo = 'Super_Admin' THEN
        -- Super Admin gets global statistics
        SELECT 
            COUNT(DISTINCT ca.id_paquete) as total_articulos,
            COUNT(DISTINCT CASE WHEN ad.id_paquete IS NOT NULL THEN ca.id_paquete END) as articulos_disponibles,
            COUNT(DISTINCT CASE WHEN s.estatus = 'Reservado' THEN s.id_paquete END) as articulos_reservados,
            COUNT(DISTINCT CASE WHEN s.estatus = 'Entregado' THEN s.id_paquete END) as articulos_entregados,
            COUNT(DISTINCT s.id_solicitud) as total_solicitudes,
            COUNT(DISTINCT CASE WHEN s.estatus IN ('Reservado', 'En_espera') THEN s.id_solicitud END) as solicitudes_pendientes,
            COUNT(DISTINCT d.id_departamento) as total_departamentos,
            COUNT(DISTINCT c.id_categoria) as total_categorias
        FROM catalogo_articulos ca
        LEFT JOIN articulos_disponibles ad ON ca.id_paquete = ad.id_paquete
        LEFT JOIN solicitudes s ON ca.id_paquete = s.id_paquete
        LEFT JOIN categorias c ON ca.id_categoria = c.id_categoria
        LEFT JOIN departamentos d ON c.id_departamento = d.id_departamento;
    ELSE
        -- Department Admin gets department-specific statistics
        SELECT 
            COUNT(DISTINCT ca.id_paquete) as total_articulos,
            COUNT(DISTINCT CASE WHEN ad.id_paquete IS NOT NULL THEN ca.id_paquete END) as articulos_disponibles,
            COUNT(DISTINCT CASE WHEN s.estatus = 'Reservado' THEN s.id_paquete END) as articulos_reservados,
            COUNT(DISTINCT CASE WHEN s.estatus = 'Entregado' THEN s.id_paquete END) as articulos_entregados,
            COUNT(DISTINCT s.id_solicitud) as total_solicitudes,
            COUNT(DISTINCT CASE WHEN s.estatus IN ('Reservado', 'En_espera') THEN s.id_solicitud END) as solicitudes_pendientes
        FROM catalogo_articulos ca
        INNER JOIN categorias c ON ca.id_categoria = c.id_categoria
        LEFT JOIN articulos_disponibles ad ON ca.id_paquete = ad.id_paquete
        LEFT JOIN solicitudes s ON ca.id_paquete = s.id_paquete
        WHERE (
            c.tipo_acceso = 'Universal'
            OR c.id_departamento = v_id_departamento
        );
    END IF;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `puede_admin_gestionar_articulo` (`p_id_usuario` INT, `p_id_paquete` VARCHAR(20)) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_puede_gestionar BOOLEAN DEFAULT FALSE;
    
    SELECT COUNT(*) > 0 INTO v_puede_gestionar
    FROM usuarios_admin ua
    INNER JOIN catalogo_articulos ca ON ca.id_paquete = p_id_paquete
    INNER JOIN categorias c ON ca.id_categoria = c.id_categoria
    WHERE ua.id_usuario = p_id_usuario
        AND ua.activo = 1
        AND c.activo = 1
        AND (
            ua.rol_tipo = 'Super_Admin'
            OR c.id_departamento = ua.id_departamento
            OR c.tipo_acceso = 'Universal'
        );
    
    RETURN v_puede_gestionar;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `puede_estudiante_ver_articulo` (`p_id_estudiante` INT, `p_id_paquete` VARCHAR(20)) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_puede_ver BOOLEAN DEFAULT FALSE;
    
    SELECT COUNT(*) > 0 INTO v_puede_ver
    FROM estudiantes e
    INNER JOIN catalogo_articulos ca ON ca.id_paquete = p_id_paquete
    INNER JOIN categorias c ON ca.id_categoria = c.id_categoria
    WHERE e.id_estudiante = p_id_estudiante
        AND c.activo = 1
        AND (
            c.tipo_acceso = 'Universal'
            OR c.id_departamento = e.id_departamento
        );
    
    RETURN v_puede_ver;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `articulos_disponibles`
-- (See below for the actual view)
--
CREATE TABLE `articulos_disponibles` (
`id_paquete` varchar(20)
,`id_categoria` int(11)
,`categoria` enum('Laboratorio','Medica','Deportes','Otro')
,`nombre` varchar(150)
,`descripcion` text
,`precio_estimado` decimal(10,2)
,`enlace_referencia` varchar(255)
,`imagen_url` varchar(255)
,`fecha_agregado` datetime
,`agregado_por` int(11)
,`nombre_categoria` varchar(100)
,`tipo_acceso` enum('Universal','Departamental')
,`categoria_departamento` int(11)
,`accesible_por_departamento` varchar(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `catalogo_articulos`
--

CREATE TABLE `catalogo_articulos` (
  `id_paquete` varchar(20) NOT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `categoria` enum('Laboratorio','Medica','Deportes','Otro') NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_estimado` decimal(10,2) DEFAULT 1000.00,
  `enlace_referencia` varchar(255) DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `fecha_agregado` datetime DEFAULT current_timestamp(),
  `agregado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `catalogo_articulos`
--

INSERT INTO `catalogo_articulos` (`id_paquete`, `id_categoria`, `categoria`, `nombre`, `descripcion`, `precio_estimado`, `enlace_referencia`, `imagen_url`, `fecha_agregado`, `agregado_por`) VALUES
('DEP-001', 1, 'Deportes', 'Balón de Fútbol', 'Balón oficial FIFA talla 5', 600.00, '', '/tsj-donaciones/uploads/articulos/DEP-001_1770567037.png', '2026-02-03 16:55:29', NULL),
('DEP-002', 1, 'Deportes', 'Red de Voleibol', 'Red profesional de voleibol', 1200.00, NULL, NULL, '2026-02-03 16:55:29', NULL),
('ICIV-001', 3, 'Laboratorio', 'PRUEBA', 'PRUEBA', 1500.00, 'https://www.google.com/', '/tsj-donaciones/uploads/articulos/ICIV-001_1770420876.png', '2026-02-06 17:34:36', 2),
('IGE-001', 7, 'Laboratorio', 'PRUEBA', 'PRUEBA', 1000.00, '', '/tsj-donaciones/uploads/articulos/IGE-001_1770567635.png', '2026-02-08 10:20:35', 2),
('IIND-001', 4, 'Laboratorio', 'PRUEBA', 'PRUEBA', 1000.00, '', NULL, '2026-02-08 10:25:00', 3),
('ISIC-001', 5, 'Laboratorio', 'Multímetro Digital', 'Multímetro digital para mediciones eléctricas', 1500.00, NULL, NULL, '2026-02-03 16:55:29', NULL),
('ISIC-002', 5, 'Laboratorio', 'Osciloscopio USB', 'Osciloscopio de 2 canales con interfaz USB', 3000.00, NULL, NULL, '2026-02-03 16:55:29', NULL),
('ISIC-003', 5, 'Laboratorio', 'Kit Arduino Uno', 'Kit de inicio Arduino con sensores', 1200.00, NULL, NULL, '2026-02-03 16:55:29', NULL),
('MED-001', 2, 'Medica', 'Tensiómetro Digital', 'Tensiómetro digital automático', 800.00, NULL, NULL, '2026-02-03 16:55:29', NULL),
('MED-002', 2, 'Medica', 'Estetoscopio', 'Estetoscopio de doble campana', 1500.00, '', NULL, '2026-02-03 16:55:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(100) NOT NULL,
  `codigo_categoria` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_acceso` enum('Universal','Departamental') DEFAULT 'Departamental',
  `id_departamento` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre_categoria`, `codigo_categoria`, `descripcion`, `tipo_acceso`, `id_departamento`, `activo`, `fecha_creacion`) VALUES
(1, 'Deportes', 'DEP', 'Artículos y equipo deportivo', 'Universal', NULL, 1, '2026-02-04 14:53:41'),
(2, 'Médica', 'MED', 'Equipo y suministros médicos', 'Universal', NULL, 1, '2026-02-04 14:53:41'),
(3, 'Laboratorio - Ingeniería Civil', 'LAB-ICIV', 'Equipo de laboratorio para Ingeniería Civil', 'Departamental', 1, 1, '2026-02-04 14:53:41'),
(4, 'Laboratorio - Ingeniería Industrial', 'LAB-IIND', 'Equipo de laboratorio para Ingeniería Industrial', 'Departamental', 2, 1, '2026-02-04 14:53:41'),
(5, 'Laboratorio - Ingeniería en Sistemas Computacionales', 'LAB-ISIC', 'Equipo de laboratorio para Ingeniería en Sistemas Computacionales', 'Departamental', 3, 1, '2026-02-04 14:53:41'),
(6, 'Laboratorio - Ingeniería Electrónica', 'LAB-IELEC', 'Equipo de laboratorio para Ingeniería Electrónica', 'Departamental', 4, 1, '2026-02-04 14:53:41'),
(7, 'Laboratorio - Ingeniería en Gestión Empresarial', 'LAB-IGE', 'Equipo de laboratorio para Ingeniería en Gestión Empresarial', 'Departamental', 5, 1, '2026-02-04 14:53:41'),
(8, 'Laboratorio - Gastronomía', 'LAB-GAST', 'Equipo de laboratorio para Gastronomía', 'Departamental', 6, 1, '2026-02-04 14:53:41'),
(9, 'Laboratorio - Ingeniería Electromecánica', 'LAB-IELEM', 'Equipo de laboratorio para Ingeniería Electromecánica', 'Departamental', 7, 1, '2026-02-04 14:53:41'),
(10, 'Laboratorio - Arquitectura', 'LAB-ARQ', 'Equipo de laboratorio para Arquitectura', 'Departamental', 8, 1, '2026-02-04 14:53:41'),
(11, 'Laboratorio - Maestría en Electrónica', 'LAB-MELEC', 'Equipo de laboratorio para Maestría en Electrónica', 'Departamental', 9, 1, '2026-02-04 14:53:41'),
(12, 'Laboratorio - Maestría en Sistemas Computacionales', 'LAB-MSIC', 'Equipo de laboratorio para Maestría en Sistemas Computacionales', 'Departamental', 10, 1, '2026-02-04 14:53:41');

--
-- Triggers `categorias`
--
DELIMITER $$
CREATE TRIGGER `after_categoria_modificada` AFTER UPDATE ON `categorias` FOR EACH ROW BEGIN
    IF NEW.activo != OLD.activo OR NEW.tipo_acceso != OLD.tipo_acceso THEN
        INSERT INTO historial_articulos (id_paquete, accion, detalles)
        SELECT 
            ca.id_paquete,
            'Modificado',
            CONCAT('Categoría modificada: ', NEW.nombre_categoria)
        FROM catalogo_articulos ca
        WHERE ca.id_categoria = NEW.id_categoria
        LIMIT 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `constancias`
--

CREATE TABLE `constancias` (
  `id_constancia` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `nombre_estudiante` varchar(150) NOT NULL,
  `numero_control` varchar(20) NOT NULL,
  `correo_institucional` varchar(100) NOT NULL,
  `pdf_filename` varchar(255) NOT NULL,
  `pdf_data` longblob DEFAULT NULL COMMENT 'Archivo PDF en formato binario',
  `fecha_generacion` datetime DEFAULT current_timestamp(),
  `enviado_por_correo` tinyint(1) DEFAULT NULL COMMENT '0=No enviado, 1=Enviado',
  `fecha_envio` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Almacena las constancias en PDF generadas para cada donador';

--
-- Triggers `constancias`
--
DELIMITER $$
CREATE TRIGGER `after_constancia_creada` AFTER INSERT ON `constancias` FOR EACH ROW BEGIN
    UPDATE solicitudes 
    SET constancia_generada = 1 
    WHERE id_solicitud = NEW.id_solicitud;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `departamentos`
--

CREATE TABLE `departamentos` (
  `id_departamento` int(11) NOT NULL,
  `nombre_departamento` varchar(100) NOT NULL,
  `codigo_departamento` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departamentos`
--

INSERT INTO `departamentos` (`id_departamento`, `nombre_departamento`, `codigo_departamento`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(0, 'Todos los Departamentos', 'TODOS', 'Departamento especial para Super Administradores con acceso total', 1, '2026-02-08 10:39:18'),
(1, 'Ingeniería Civil', 'ICIV', 'Carrera de Ingeniería Civil', 1, '2026-02-04 14:53:40'),
(2, 'Ingeniería Industrial', 'IIND', 'Carrera de Ingeniería Industrial', 1, '2026-02-04 14:53:40'),
(3, 'Ingeniería en Sistemas Computacionales', 'ISIC', 'Carrera de Ingeniería en Sistemas Computacionales', 1, '2026-02-04 14:53:40'),
(4, 'Ingeniería Electrónica', 'IELEC', 'Carrera de Ingeniería Electrónica', 1, '2026-02-04 14:53:40'),
(5, 'Ingeniería en Gestión Empresarial', 'IGE', 'Carrera de Ingeniería en Gestión Empresarial', 1, '2026-02-04 14:53:40'),
(6, 'Gastronomía', 'GAST', 'Carrera de Gastronomía', 1, '2026-02-04 14:53:40'),
(7, 'Ingeniería Electromecánica', 'IELEM', 'Carrera de Ingeniería Electromecánica', 1, '2026-02-04 14:53:40'),
(8, 'Arquitectura', 'ARQ', 'Carrera de Arquitectura', 1, '2026-02-04 14:53:40'),
(9, 'Maestría en Electrónica', 'MELEC', 'Programa de Maestría en Electrónica', 1, '2026-02-04 14:53:40'),
(10, 'Maestría en Sistemas Computacionales', 'MSIC', 'Programa de Maestría en Sistemas Computacionales', 1, '2026-02-04 14:53:40');

-- --------------------------------------------------------

--
-- Table structure for table `donadores_detalle`
--

CREATE TABLE `donadores_detalle` (
  `id_detalle` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `numero_control` varchar(20) NOT NULL,
  `correo_institucional` varchar(100) NOT NULL,
  `es_representante` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donadores_detalle`
--

INSERT INTO `donadores_detalle` (`id_detalle`, `id_solicitud`, `nombre_completo`, `numero_control`, `correo_institucional`, `es_representante`) VALUES
(4, 4, 'JENNIFER', '12345', 'za220120132@zapopan.tecmm.edu.mx', 1),
(5, 4, 'PRUEBA', '123456', 'jenniferarellanovi@gmail.com', 0),
(6, 5, 'PRUEBA', '10', 'za220120132@zapopan.tecmm.edu.mx', 1),
(7, 5, 'JUANITO', '20', 'cixxatzvote1@gmail.com', 0),
(8, 6, 'PRUEBA', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(9, 7, 'PRUEBA', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(10, 8, 'PRUEBA2', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(11, 9, 'PRUEBA 3', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(13, 11, 'PRUEBA', 'GESTION', 'cixxatzvote1@gmail.com', 1),
(14, 12, 'PRUEBA', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(15, 13, 'PRUEBA', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(16, 14, 'PRUEBA', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(17, 15, 'PRUEBA', '12345', 'cixxatzvote1@gmail.com', 1),
(19, 17, 'PRUEBA', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(20, 18, 'PRUEBA JENNIER', '123456', 'cixxatzvote1@gmail.com', 1),
(21, 19, 'PRUEBA JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(22, 20, 'PRUEBA', 'PRUEBA', 'cixxatzvote1@gmail.com', 1),
(23, 21, 'JENNIFER ARELLANO', '12345', 'cixxatzvote1@gmail.com', 1),
(24, 22, 'PRUEBA ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(25, 23, 'PRUEBA ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(26, 24, 'ARELLANO VILLASEÑOR JENNIFER PRUEBA 1', '12345', 'cixxatzvote1@gmail.com', 1),
(27, 25, 'ARELLANO VILLASEÑOR JENNIFER PRUEBA 2', '12345', 'jennyfermiryam@gmail.com', 1),
(28, 26, 'PRUEBA 3 ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(29, 27, 'PRUEBA 4 ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(30, 28, 'PRUEBA 5 ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(31, 29, 'PRUEBA 6 ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(32, 30, 'PRUEBA ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(33, 31, 'PRUEBA ARELLANO VILLASEÑOR JENNIFER', '12345', 'cixxatzvote1@gmail.com', 1),
(34, 32, 'ARELLANO VILLASEÑOR KELLY ANAHI', '12345P', 'cixxatzvote1@gmail.com', 1),
(35, 32, 'ARELLANO VILLASEÑOR JENNIFER MIRIAM', '123456P', 'cixxatzvote2@gmail.com', 0),
(36, 33, 'PRUEBA PEPITO', '12345', 'cixxatzvote1@gmail.com', 1),
(37, 34, 'JOSÉ', '123J', 'cixxatzvote1@gmail.com', 1),
(38, 34, 'PEPITO', '1234P', 'cixxatzvote2@gmail.com', 0);

-- --------------------------------------------------------

--
-- Table structure for table `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `id_departamento` int(11) DEFAULT NULL,
  `carrera` varchar(100) DEFAULT NULL,
  `picture_url` varchar(500) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `email`, `google_id`, `nombre_completo`, `id_departamento`, `carrera`, `picture_url`, `fecha_registro`, `ultimo_acceso`) VALUES
(1, 'za220120132@zapopan.tecmm.edu.mx', '115434396931099270156', 'JENNIFER MIRIAM ARELLANO VILLASE�OR', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJAvq1Cy_oheXs525Lj_kT8GXM8V8fUxYlx_x4JJc1J-0NuDwk=s96-c', '2026-02-03 16:55:51', '2026-02-12 16:32:57'),
(2, 'ing.sistemas@zapopan.tecmm.edu.mx', '104459011722970402395', 'DIV. SISTEMAS', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocKKuVkldEAbFCl_YzG4Y7tzOuYRy0kehiqP4X2qUzGpQD5yWZg=s96-c', '2026-02-06 17:03:34', '2026-02-06 17:03:34');

-- --------------------------------------------------------

--
-- Table structure for table `historial_articulos`
--

CREATE TABLE `historial_articulos` (
  `id_historial` int(11) NOT NULL,
  `id_paquete` varchar(20) NOT NULL,
  `accion` enum('Agregado','Reservado','Liberado','Entregado','Modificado') NOT NULL,
  `id_solicitud` int(11) DEFAULT NULL,
  `realizado_por` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `fecha_accion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `historial_articulos`
--

INSERT INTO `historial_articulos` (`id_historial`, `id_paquete`, `accion`, `id_solicitud`, `realizado_por`, `detalles`, `fecha_accion`) VALUES
(1, 'ISIC-001', 'Reservado', NULL, NULL, NULL, '2026-02-03 17:05:50'),
(2, 'ISIC-001', 'Reservado', NULL, 2, 'Solicitud aprobada', '2026-02-03 17:07:18'),
(3, 'ISIC-001', 'Modificado', NULL, 2, NULL, '2026-02-03 17:07:18'),
(4, 'ISIC-001', 'Entregado', NULL, 2, 'Artículo entregado físicamente', '2026-02-03 17:09:20'),
(5, 'ISIC-001', 'Entregado', NULL, 2, NULL, '2026-02-03 17:09:20'),
(6, 'MED-001', 'Reservado', NULL, NULL, NULL, '2026-02-04 16:37:22'),
(7, 'MED-001', 'Modificado', NULL, 3, NULL, '2026-02-04 16:51:27'),
(8, 'MED-001', 'Liberado', NULL, 3, NULL, '2026-02-04 16:51:27'),
(9, 'ISIC-003', 'Reservado', NULL, NULL, NULL, '2026-02-06 16:53:04'),
(10, 'ICIV-001', 'Agregado', NULL, 2, NULL, '2026-02-06 17:34:36'),
(11, 'DEP-001', 'Modificado', NULL, 2, NULL, '2026-02-08 09:09:58'),
(12, 'DEP-001', 'Modificado', NULL, 2, NULL, '2026-02-08 09:57:31'),
(13, 'MED-002', 'Modificado', NULL, 2, NULL, '2026-02-08 10:01:41'),
(14, 'MED-002', 'Modificado', NULL, 2, NULL, '2026-02-08 10:10:07'),
(15, 'DEP-001', 'Modificado', NULL, 2, NULL, '2026-02-08 10:10:37'),
(16, 'IGE-001', 'Agregado', NULL, 2, NULL, '2026-02-08 10:20:35'),
(17, 'IIND-001', 'Agregado', NULL, 3, NULL, '2026-02-08 10:25:01'),
(18, 'ISIC-003', 'Reservado', 4, NULL, NULL, '2026-02-08 12:04:06'),
(19, 'ISIC-003', 'Modificado', 4, 2, NULL, '2026-02-08 12:53:41'),
(20, 'ISIC-003', 'Liberado', 4, 2, NULL, '2026-02-08 12:53:41'),
(21, 'ISIC-001', 'Reservado', 5, NULL, NULL, '2026-02-08 12:55:23'),
(22, 'ISIC-001', 'Modificado', 5, 2, NULL, '2026-02-08 12:55:53'),
(23, 'ISIC-001', 'Liberado', 5, 2, NULL, '2026-02-08 12:55:53'),
(24, 'IIND-001', 'Reservado', 6, NULL, NULL, '2026-02-08 12:56:57'),
(25, 'IIND-001', 'Reservado', 6, 3, 'Solicitud aprobada', '2026-02-08 12:57:31'),
(26, 'IIND-001', 'Modificado', 6, 3, NULL, '2026-02-08 12:57:31'),
(27, 'IIND-001', 'Entregado', 6, 3, 'Artículo entregado físicamente', '2026-02-08 12:58:00'),
(28, 'IIND-001', 'Entregado', 6, 3, NULL, '2026-02-08 12:58:00'),
(29, 'ISIC-002', 'Reservado', 7, NULL, NULL, '2026-02-08 13:00:01'),
(30, 'ISIC-002', 'Modificado', 7, 2, NULL, '2026-02-08 13:00:43'),
(31, 'ISIC-002', 'Liberado', 7, 2, NULL, '2026-02-08 13:00:43'),
(32, 'ISIC-003', 'Reservado', 8, NULL, NULL, '2026-02-08 13:02:23'),
(33, 'ISIC-003', 'Reservado', 8, 2, 'Solicitud aprobada', '2026-02-08 13:02:37'),
(34, 'ISIC-003', 'Modificado', 8, 2, NULL, '2026-02-08 13:02:37'),
(35, 'ISIC-003', 'Entregado', 8, 2, 'Artículo entregado físicamente', '2026-02-08 13:02:44'),
(36, 'ISIC-003', 'Entregado', 8, 2, NULL, '2026-02-08 13:02:44'),
(37, 'ISIC-003', 'Reservado', 9, NULL, NULL, '2026-02-08 13:07:09'),
(38, 'ISIC-003', 'Modificado', 9, 2, NULL, '2026-02-08 13:07:23'),
(39, 'ISIC-003', 'Liberado', 9, 2, NULL, '2026-02-08 13:07:23'),
(40, 'IIND-001', 'Reservado', NULL, NULL, NULL, '2026-02-08 13:27:43'),
(41, 'IGE-001', 'Reservado', 11, NULL, NULL, '2026-02-08 14:40:46'),
(42, 'IGE-001', 'Modificado', 11, 7, NULL, '2026-02-08 14:44:51'),
(43, 'IGE-001', 'Liberado', 11, 7, NULL, '2026-02-08 14:44:51'),
(44, 'ISIC-003', 'Reservado', 12, NULL, NULL, '2026-02-08 17:45:17'),
(45, 'ISIC-003', 'Reservado', 12, 2, 'Solicitud aprobada', '2026-02-08 17:46:16'),
(46, 'ISIC-003', 'Modificado', 12, 2, NULL, '2026-02-08 17:46:16'),
(47, 'ISIC-003', 'Entregado', 12, 2, 'Artículo entregado físicamente', '2026-02-08 17:46:43'),
(48, 'ISIC-003', 'Entregado', 12, 2, NULL, '2026-02-08 17:46:43'),
(49, 'ISIC-002', 'Reservado', 13, NULL, NULL, '2026-02-08 17:49:26'),
(50, 'ISIC-002', 'Reservado', 13, 2, 'Solicitud aprobada', '2026-02-08 17:49:39'),
(51, 'ISIC-002', 'Modificado', 13, 2, NULL, '2026-02-08 17:49:39'),
(52, 'ISIC-002', 'Entregado', 13, 2, 'Artículo entregado físicamente', '2026-02-08 17:50:07'),
(53, 'ISIC-002', 'Entregado', 13, 2, NULL, '2026-02-08 17:50:07'),
(54, 'ISIC-003', 'Reservado', 14, NULL, NULL, '2026-02-08 17:52:58'),
(55, 'ISIC-003', 'Reservado', 14, 2, 'Solicitud aprobada', '2026-02-08 17:53:16'),
(56, 'ISIC-003', 'Modificado', 14, 2, NULL, '2026-02-08 17:53:16'),
(57, 'ISIC-003', 'Entregado', 14, 2, 'Artículo entregado físicamente', '2026-02-08 18:11:03'),
(58, 'ISIC-003', 'Entregado', 14, 2, NULL, '2026-02-08 18:11:03'),
(59, 'ISIC-002', 'Reservado', 15, NULL, NULL, '2026-02-08 20:02:08'),
(60, 'ISIC-002', 'Modificado', 15, 2, NULL, '2026-02-08 20:02:31'),
(61, 'ISIC-002', 'Liberado', 15, 2, NULL, '2026-02-08 20:02:31'),
(62, 'ISIC-003', 'Reservado', NULL, NULL, NULL, '2026-02-09 14:54:51'),
(63, 'ISIC-003', 'Reservado', NULL, 2, 'Solicitud aprobada', '2026-02-09 14:55:00'),
(64, 'ISIC-003', 'Modificado', NULL, 2, NULL, '2026-02-09 14:55:00'),
(65, 'ISIC-003', 'Entregado', NULL, 2, 'Artículo entregado físicamente', '2026-02-09 14:55:04'),
(66, 'ISIC-003', 'Entregado', NULL, 2, NULL, '2026-02-09 14:55:04'),
(67, 'ISIC-003', 'Reservado', 17, NULL, NULL, '2026-02-09 15:00:32'),
(68, 'ISIC-003', 'Reservado', 17, 2, 'Solicitud aprobada', '2026-02-09 15:01:56'),
(69, 'ISIC-003', 'Modificado', 17, 2, NULL, '2026-02-09 15:01:56'),
(70, 'ISIC-003', 'Modificado', 17, 2, NULL, '2026-02-09 15:02:32'),
(71, 'ISIC-003', 'Entregado', 17, 2, 'Artículo entregado físicamente', '2026-02-09 15:05:56'),
(72, 'ISIC-003', 'Entregado', 17, 2, NULL, '2026-02-09 15:05:56'),
(73, 'ISIC-003', 'Reservado', 18, NULL, NULL, '2026-02-09 16:01:01'),
(74, 'ISIC-003', 'Reservado', 18, 2, 'Solicitud aprobada', '2026-02-09 16:01:14'),
(75, 'ISIC-003', 'Modificado', 18, 2, NULL, '2026-02-09 16:01:14'),
(76, 'ISIC-003', 'Entregado', 18, 2, 'Artículo entregado físicamente', '2026-02-09 16:01:43'),
(77, 'ISIC-003', 'Entregado', 18, 2, NULL, '2026-02-09 16:01:43'),
(78, 'ISIC-003', 'Reservado', 19, NULL, NULL, '2026-02-09 16:37:40'),
(79, 'ISIC-003', 'Reservado', 19, 2, 'Solicitud aprobada', '2026-02-09 16:38:00'),
(80, 'ISIC-003', 'Modificado', 19, 2, NULL, '2026-02-09 16:38:00'),
(81, 'ISIC-003', 'Entregado', 19, 2, 'Artículo entregado físicamente', '2026-02-09 16:38:58'),
(82, 'ISIC-003', 'Entregado', 19, 2, NULL, '2026-02-09 16:38:58'),
(83, 'ISIC-003', 'Entregado', 19, 2, NULL, '2026-02-09 16:47:28'),
(84, 'ISIC-003', 'Reservado', 20, NULL, NULL, '2026-02-10 15:42:28'),
(85, 'ISIC-003', 'Reservado', 20, 2, 'Solicitud aprobada', '2026-02-10 15:42:39'),
(86, 'ISIC-003', 'Modificado', 20, 2, NULL, '2026-02-10 15:42:39'),
(87, 'ISIC-003', 'Entregado', 20, 2, 'Artículo entregado físicamente', '2026-02-10 15:42:51'),
(88, 'ISIC-003', 'Entregado', 20, 2, NULL, '2026-02-10 15:42:51'),
(89, 'ISIC-003', 'Reservado', 21, NULL, NULL, '2026-02-10 15:47:29'),
(90, 'ISIC-003', 'Reservado', 21, 2, 'Solicitud aprobada', '2026-02-10 15:47:54'),
(91, 'ISIC-003', 'Modificado', 21, 2, NULL, '2026-02-10 15:47:54'),
(92, 'ISIC-003', 'Entregado', 21, 2, 'Artículo entregado físicamente', '2026-02-10 15:48:14'),
(93, 'ISIC-003', 'Entregado', 21, 2, NULL, '2026-02-10 15:48:14'),
(94, 'ISIC-003', 'Reservado', 22, NULL, NULL, '2026-02-10 15:56:28'),
(95, 'ISIC-003', 'Reservado', 22, 2, 'Solicitud aprobada', '2026-02-10 15:56:59'),
(96, 'ISIC-003', 'Modificado', 22, 2, NULL, '2026-02-10 15:56:59'),
(97, 'ISIC-003', 'Entregado', 22, 2, 'Artículo entregado físicamente', '2026-02-10 15:57:18'),
(98, 'ISIC-003', 'Entregado', 22, 2, NULL, '2026-02-10 15:57:18'),
(99, 'ISIC-003', 'Reservado', 23, NULL, NULL, '2026-02-10 16:00:33'),
(100, 'ISIC-003', 'Reservado', 23, 2, 'Solicitud aprobada', '2026-02-10 16:00:48'),
(101, 'ISIC-003', 'Modificado', 23, 2, NULL, '2026-02-10 16:00:48'),
(102, 'ISIC-003', 'Entregado', 23, 2, 'Artículo entregado físicamente', '2026-02-10 16:01:02'),
(103, 'ISIC-003', 'Entregado', 23, 2, NULL, '2026-02-10 16:01:02'),
(104, 'ISIC-003', 'Reservado', 24, NULL, NULL, '2026-02-10 16:09:26'),
(105, 'ISIC-003', 'Reservado', 24, 2, 'Solicitud aprobada', '2026-02-10 16:09:36'),
(106, 'ISIC-003', 'Modificado', 24, 2, NULL, '2026-02-10 16:09:36'),
(107, 'ISIC-003', 'Entregado', 24, 2, 'Artículo entregado físicamente', '2026-02-10 16:09:49'),
(108, 'ISIC-003', 'Entregado', 24, 2, NULL, '2026-02-10 16:09:49'),
(109, 'ISIC-003', 'Reservado', 25, NULL, NULL, '2026-02-10 16:17:50'),
(110, 'ISIC-003', 'Reservado', 25, 2, 'Solicitud aprobada', '2026-02-10 16:18:02'),
(111, 'ISIC-003', 'Modificado', 25, 2, NULL, '2026-02-10 16:18:02'),
(112, 'ISIC-003', 'Entregado', 25, 2, 'Artículo entregado físicamente', '2026-02-10 16:18:08'),
(113, 'ISIC-003', 'Entregado', 25, 2, NULL, '2026-02-10 16:18:08'),
(114, 'ISIC-003', 'Reservado', 26, NULL, NULL, '2026-02-10 16:21:16'),
(115, 'ISIC-003', 'Reservado', 26, 2, 'Solicitud aprobada', '2026-02-10 16:21:29'),
(116, 'ISIC-003', 'Modificado', 26, 2, NULL, '2026-02-10 16:21:29'),
(117, 'ISIC-003', 'Entregado', 26, 2, 'Artículo entregado físicamente', '2026-02-10 16:21:45'),
(118, 'ISIC-003', 'Entregado', 26, 2, NULL, '2026-02-10 16:21:45'),
(119, 'ISIC-003', 'Entregado', 26, 2, NULL, '2026-02-10 16:22:09'),
(120, 'ISIC-003', 'Reservado', 27, NULL, NULL, '2026-02-10 16:34:58'),
(121, 'ISIC-003', 'Reservado', 27, 2, 'Solicitud aprobada', '2026-02-10 16:35:08'),
(122, 'ISIC-003', 'Modificado', 27, 2, NULL, '2026-02-10 16:35:08'),
(123, 'ISIC-003', 'Entregado', 27, 2, 'Artículo entregado físicamente', '2026-02-10 16:35:16'),
(124, 'ISIC-003', 'Entregado', 27, 2, NULL, '2026-02-10 16:35:16'),
(125, 'ISIC-003', 'Reservado', 28, NULL, NULL, '2026-02-10 16:41:49'),
(126, 'ISIC-003', 'Reservado', 28, 2, 'Solicitud aprobada', '2026-02-10 16:42:05'),
(127, 'ISIC-003', 'Modificado', 28, 2, NULL, '2026-02-10 16:42:05'),
(128, 'ISIC-003', 'Entregado', 28, 2, 'Artículo entregado físicamente', '2026-02-10 16:42:12'),
(129, 'ISIC-003', 'Entregado', 28, 2, NULL, '2026-02-10 16:42:12'),
(130, 'ISIC-003', 'Reservado', 29, NULL, NULL, '2026-02-10 16:56:25'),
(131, 'ISIC-003', 'Reservado', 29, 2, 'Solicitud aprobada', '2026-02-10 16:56:37'),
(132, 'ISIC-003', 'Modificado', 29, 2, NULL, '2026-02-10 16:56:37'),
(133, 'ISIC-003', 'Entregado', 29, 2, 'Artículo entregado físicamente', '2026-02-10 16:56:43'),
(134, 'ISIC-003', 'Entregado', 29, 2, NULL, '2026-02-10 16:56:43'),
(135, 'ISIC-003', 'Reservado', 30, NULL, NULL, '2026-02-10 17:18:52'),
(136, 'ISIC-003', 'Reservado', 30, 2, 'Solicitud aprobada', '2026-02-10 17:19:01'),
(137, 'ISIC-003', 'Modificado', 30, 2, NULL, '2026-02-10 17:19:01'),
(138, 'ISIC-003', 'Entregado', 30, 2, 'Artículo entregado físicamente', '2026-02-10 17:19:07'),
(139, 'ISIC-003', 'Entregado', 30, 2, NULL, '2026-02-10 17:19:07'),
(140, 'ISIC-003', 'Reservado', 31, NULL, NULL, '2026-02-12 16:33:32'),
(141, 'ISIC-003', 'Reservado', 31, 2, 'Solicitud aprobada', '2026-02-12 16:33:43'),
(142, 'ISIC-003', 'Modificado', 31, 2, NULL, '2026-02-12 16:33:43'),
(143, 'ISIC-003', 'Entregado', 31, 2, 'Artículo entregado físicamente', '2026-02-12 16:34:03'),
(144, 'ISIC-003', 'Entregado', 31, 2, NULL, '2026-02-12 16:34:03'),
(145, 'ISIC-003', 'Entregado', 31, 2, NULL, '2026-02-12 16:34:14'),
(146, 'ISIC-001', 'Reservado', 32, NULL, NULL, '2026-02-12 16:38:36'),
(147, 'ISIC-001', 'Reservado', 32, 2, 'Solicitud aprobada', '2026-02-12 16:39:10'),
(148, 'ISIC-001', 'Modificado', 32, 2, NULL, '2026-02-12 16:39:10'),
(149, 'ISIC-001', 'Entregado', 32, 2, 'Artículo entregado físicamente', '2026-02-12 16:39:21'),
(150, 'ISIC-001', 'Entregado', 32, 2, NULL, '2026-02-12 16:39:21'),
(151, 'MED-002', 'Reservado', 33, NULL, NULL, '2026-02-12 17:10:25'),
(152, 'MED-002', 'Reservado', 33, 2, 'Solicitud aprobada', '2026-02-12 17:10:36'),
(153, 'MED-002', 'Modificado', 33, 2, NULL, '2026-02-12 17:10:36'),
(154, 'MED-002', 'Entregado', 33, 2, 'Artículo entregado físicamente', '2026-02-12 17:10:51'),
(155, 'MED-002', 'Entregado', 33, 2, NULL, '2026-02-12 17:10:51'),
(156, 'DEP-001', 'Reservado', 34, NULL, NULL, '2026-02-12 18:23:49'),
(157, 'DEP-001', 'Reservado', 34, 2, 'Solicitud aprobada', '2026-02-12 18:24:07'),
(158, 'DEP-001', 'Modificado', 34, 2, NULL, '2026-02-12 18:24:07'),
(159, 'DEP-001', 'Entregado', 34, 2, 'Artículo entregado físicamente', '2026-02-12 18:24:45'),
(160, 'DEP-001', 'Entregado', 34, 2, NULL, '2026-02-12 18:24:45');

-- --------------------------------------------------------

--
-- Table structure for table `solicitudes`
--

CREATE TABLE `solicitudes` (
  `id_solicitud` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_paquete` varchar(20) NOT NULL,
  `email_contacto` varchar(100) NOT NULL,
  `carrera` varchar(50) NOT NULL,
  `tipo_donacion` varchar(50) DEFAULT NULL,
  `estatus` enum('Reservado','Aprobado','En_espera','Entregado','Rechazado','Expirado') DEFAULT 'Reservado',
  `fecha_solicitud` datetime DEFAULT current_timestamp(),
  `fecha_expiracion` datetime NOT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `recibido_por` int(11) DEFAULT NULL,
  `notas_admin` text DEFAULT NULL,
  `constancia_generada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `solicitudes`
--

INSERT INTO `solicitudes` (`id_solicitud`, `id_estudiante`, `id_paquete`, `email_contacto`, `carrera`, `tipo_donacion`, `estatus`, `fecha_solicitud`, `fecha_expiracion`, `fecha_aprobacion`, `fecha_entrega`, `aprobado_por`, `recibido_por`, `notas_admin`, `constancia_generada`) VALUES
(4, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 12:04:06', '2026-02-13 12:04:06', NULL, NULL, NULL, NULL, NULL, 0),
(5, 1, 'ISIC-001', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 12:55:23', '2026-02-13 12:55:23', NULL, NULL, NULL, NULL, NULL, 1),
(6, 1, 'IIND-001', 'za220120132@zapopan.tecmm.edu.mx', 'IIND', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 12:56:57', '2026-02-13 12:56:57', '2026-02-08 12:57:31', '2026-02-08 12:58:00', 3, 3, NULL, 0),
(7, 1, 'ISIC-002', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 13:00:01', '2026-02-13 13:00:01', NULL, NULL, NULL, NULL, NULL, 0),
(8, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 13:02:23', '2026-02-13 13:02:23', '2026-02-08 13:02:37', '2026-02-08 13:02:44', 2, 2, NULL, 0),
(9, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 13:07:09', '2026-02-13 13:07:09', NULL, NULL, NULL, NULL, NULL, 0),
(11, 1, 'IGE-001', 'za220120132@zapopan.tecmm.edu.mx', 'IGE', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 14:40:46', '2026-02-13 14:40:46', NULL, NULL, NULL, NULL, NULL, 0),
(12, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 17:45:17', '2026-02-13 17:45:17', '2026-02-08 17:46:16', '2026-02-08 17:46:43', 2, 2, NULL, 0),
(13, 1, 'ISIC-002', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 17:49:26', '2026-02-13 17:49:26', '2026-02-08 17:49:39', '2026-02-08 17:50:07', 2, 2, NULL, 0),
(14, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 17:52:58', '2026-02-13 17:52:58', '2026-02-08 17:53:16', '2026-02-08 18:11:03', 2, 2, NULL, 0),
(15, 1, 'ISIC-002', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-08 20:02:08', '2026-02-13 20:02:08', NULL, NULL, NULL, NULL, NULL, 0),
(17, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-09 15:00:32', '2026-02-14 15:00:32', '2026-02-09 15:02:32', '2026-02-09 15:05:56', 2, 2, NULL, 0),
(18, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-09 16:01:01', '2026-02-14 16:01:01', '2026-02-09 16:01:14', '2026-02-09 16:01:43', 2, 2, NULL, 0),
(19, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-09 16:37:40', '2026-02-14 16:37:40', '2026-02-09 16:38:00', '2026-02-09 16:47:28', 2, 2, NULL, 0),
(20, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 15:42:28', '2026-02-15 15:42:28', '2026-02-10 15:42:39', '2026-02-10 15:42:51', 2, 2, NULL, 0),
(21, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 15:47:29', '2026-02-15 15:47:29', '2026-02-10 15:47:54', '2026-02-10 15:48:14', 2, 2, NULL, 0),
(22, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 15:56:28', '2026-02-15 15:56:28', '2026-02-10 15:56:59', '2026-02-10 15:57:18', 2, 2, NULL, 0),
(23, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 16:00:33', '2026-02-15 16:00:33', '2026-02-10 16:00:48', '2026-02-10 16:01:02', 2, 2, NULL, 0),
(24, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 16:09:26', '2026-02-15 16:09:26', '2026-02-10 16:09:36', '2026-02-10 16:09:49', 2, 2, NULL, 0),
(25, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 16:17:50', '2026-02-15 16:17:50', '2026-02-10 16:18:02', '2026-02-10 16:18:08', 2, 2, NULL, 0),
(26, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 16:21:16', '2026-02-15 16:21:16', '2026-02-10 16:21:29', '2026-02-10 16:22:09', 2, 2, NULL, 0),
(27, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 16:34:58', '2026-02-15 16:34:58', '2026-02-10 16:35:08', '2026-02-10 16:35:16', 2, 2, NULL, 0),
(28, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 16:41:49', '2026-02-15 16:41:49', '2026-02-10 16:42:05', '2026-02-10 16:42:12', 2, 2, NULL, 0),
(29, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 16:56:25', '2026-02-15 16:56:25', '2026-02-10 16:56:37', '2026-02-10 16:56:43', 2, 2, NULL, 0),
(30, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-10 17:18:52', '2026-02-15 17:18:52', '2026-02-10 17:19:01', '2026-02-10 17:19:07', 2, 2, NULL, 0),
(31, 1, 'ISIC-003', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-12 16:33:32', '2026-02-17 16:33:32', '2026-02-12 16:33:43', '2026-02-12 16:34:14', 2, 2, NULL, 0),
(32, 1, 'ISIC-001', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Laboratorios y Talleres', 'Rechazado', '2026-02-12 16:38:36', '2026-02-17 16:38:36', '2026-02-12 16:39:10', '2026-02-12 16:39:21', 2, 2, NULL, 0),
(33, 1, 'MED-002', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Médica', 'Rechazado', '2026-02-12 17:10:25', '2026-02-17 17:10:25', '2026-02-12 17:10:36', '2026-02-12 17:10:51', 2, 2, NULL, 0),
(34, 1, 'DEP-001', 'za220120132@zapopan.tecmm.edu.mx', 'ISIC', 'Deportes', 'Entregado', '2026-02-12 18:23:49', '2026-02-17 18:23:49', '2026-02-12 18:24:07', '2026-02-12 18:24:45', 2, 2, NULL, 0);

--
-- Triggers `solicitudes`
--
DELIMITER $$
CREATE TRIGGER `after_articulo_entregado` AFTER UPDATE ON `solicitudes` FOR EACH ROW BEGIN
    IF NEW.estatus = 'Entregado' AND OLD.estatus != 'Entregado' THEN
        INSERT INTO historial_articulos (id_paquete, accion, id_solicitud, realizado_por, detalles)
        VALUES (NEW.id_paquete, 'Entregado', NEW.id_solicitud, NEW.recibido_por, 'Artículo entregado físicamente');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_solicitud_aprobada` AFTER UPDATE ON `solicitudes` FOR EACH ROW BEGIN
    IF NEW.estatus = 'Aprobado' AND OLD.estatus != 'Aprobado' THEN
        INSERT INTO historial_articulos (id_paquete, accion, id_solicitud, realizado_por, detalles)
        VALUES (NEW.id_paquete, 'Reservado', NEW.id_solicitud, NEW.aprobado_por, 'Solicitud aprobada');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios_admin`
--

CREATE TABLE `usuarios_admin` (
  `id_usuario` int(11) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `picture_url` varchar(500) DEFAULT NULL,
  `rol` enum('Admin') DEFAULT 'Admin',
  `rol_tipo` enum('Super_Admin','Admin_Departamento') DEFAULT 'Admin_Departamento',
  `id_departamento` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios_admin`
--

INSERT INTO `usuarios_admin` (`id_usuario`, `nombre_completo`, `email`, `password_hash`, `google_id`, `picture_url`, `rol`, `rol_tipo`, `id_departamento`, `activo`, `fecha_creacion`, `ultimo_acceso`) VALUES
(1, 'Administrador Sistema', 'cixxatzvote2@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'Admin', 'Admin_Departamento', 3, 1, '2026-02-03 16:55:29', NULL),
(2, 'Prueba', 'jennyfermiryam@gmail.com', '$2y$10$jQZzfxs5Uvbh7HzGDAJjc.3Q1F35kb6YmDjJlquaeyVTbHH8.lYbC', NULL, NULL, 'Admin', 'Super_Admin', 0, 1, '2026-02-03 16:57:02', '2026-02-12 16:32:08'),
(3, 'Prueba Admin Industrial', 'cixxatzvote1@gmail.com', '$2y$10$IVSLhR5uEqnlxO/FsMYOgO6I.uxWAcx195P9zZHNeDwi9y82oooXq', NULL, NULL, 'Admin', 'Admin_Departamento', 2, 1, '2026-02-04 14:59:23', '2026-02-10 17:23:02'),
(7, 'Prueba Gestion Empresarial', 'cixxatzvote3@gmail.com', '$2y$10$DoOZy8M2adPvAmoMcFKWA.94BekETOIMZldEj8tQLRNt6RVJAWlVy', NULL, NULL, 'Admin', 'Admin_Departamento', 5, 1, '2026-02-08 14:32:21', '2026-02-08 14:41:34');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_permisos_admin`
-- (See below for the actual view)
--
CREATE TABLE `vista_permisos_admin` (
`id_usuario` int(11)
,`admin_nombre` varchar(150)
,`admin_email` varchar(100)
,`rol_tipo` enum('Super_Admin','Admin_Departamento')
,`admin_departamento` int(11)
,`id_categoria` int(11)
,`nombre_categoria` varchar(100)
,`codigo_categoria` varchar(20)
,`tipo_acceso` enum('Universal','Departamental')
,`tipo_permiso` varchar(20)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_permisos_estudiante`
-- (See below for the actual view)
--
CREATE TABLE `vista_permisos_estudiante` (
`id_estudiante` int(11)
,`estudiante_email` varchar(100)
,`estudiante_nombre` varchar(150)
,`estudiante_departamento` int(11)
,`id_categoria` int(11)
,`nombre_categoria` varchar(100)
,`codigo_categoria` varchar(20)
,`tipo_acceso` enum('Universal','Departamental')
,`tipo_permiso` varchar(20)
);

-- --------------------------------------------------------

--
-- Structure for view `articulos_disponibles`
--
DROP TABLE IF EXISTS `articulos_disponibles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `articulos_disponibles`  AS SELECT `ca`.`id_paquete` AS `id_paquete`, `ca`.`id_categoria` AS `id_categoria`, `ca`.`categoria` AS `categoria`, `ca`.`nombre` AS `nombre`, `ca`.`descripcion` AS `descripcion`, `ca`.`precio_estimado` AS `precio_estimado`, `ca`.`enlace_referencia` AS `enlace_referencia`, `ca`.`imagen_url` AS `imagen_url`, `ca`.`fecha_agregado` AS `fecha_agregado`, `ca`.`agregado_por` AS `agregado_por`, `cat`.`nombre_categoria` AS `nombre_categoria`, `cat`.`tipo_acceso` AS `tipo_acceso`, `cat`.`id_departamento` AS `categoria_departamento`, CASE WHEN `cat`.`tipo_acceso` = 'Universal' THEN 'all' ELSE cast(`cat`.`id_departamento` as char charset utf8mb4) END AS `accesible_por_departamento` FROM ((`catalogo_articulos` `ca` join `categorias` `cat` on(`ca`.`id_categoria` = `cat`.`id_categoria`)) left join `solicitudes` `s` on(`ca`.`id_paquete` = `s`.`id_paquete` and `s`.`estatus` in ('Reservado','Aprobado','En_espera'))) WHERE `cat`.`activo` = 1 AND (`s`.`id_solicitud` is null OR `s`.`estatus` = 'Expirado' OR `s`.`fecha_expiracion` < current_timestamp()) ;

-- --------------------------------------------------------

--
-- Structure for view `vista_permisos_admin`
--
DROP TABLE IF EXISTS `vista_permisos_admin`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_permisos_admin`  AS SELECT `ua`.`id_usuario` AS `id_usuario`, `ua`.`nombre_completo` AS `admin_nombre`, `ua`.`email` AS `admin_email`, `ua`.`rol_tipo` AS `rol_tipo`, `ua`.`id_departamento` AS `admin_departamento`, `c`.`id_categoria` AS `id_categoria`, `c`.`nombre_categoria` AS `nombre_categoria`, `c`.`codigo_categoria` AS `codigo_categoria`, `c`.`tipo_acceso` AS `tipo_acceso`, CASE WHEN `ua`.`rol_tipo` = 'Super_Admin' THEN 'Acceso Total' WHEN `ua`.`rol_tipo` = 'Admin_Departamento' AND `c`.`id_departamento` = `ua`.`id_departamento` THEN 'Acceso Departamental' WHEN `ua`.`rol_tipo` = 'Admin_Departamento' AND `c`.`tipo_acceso` = 'Universal' THEN 'Acceso Universal' ELSE 'Sin Acceso' END AS `tipo_permiso` FROM (`usuarios_admin` `ua` join `categorias` `c`) WHERE `ua`.`activo` = 1 AND `c`.`activo` = 1 AND (`ua`.`rol_tipo` = 'Super_Admin' OR `c`.`tipo_acceso` = 'Universal' OR `c`.`id_departamento` = `ua`.`id_departamento`) ;

-- --------------------------------------------------------

--
-- Structure for view `vista_permisos_estudiante`
--
DROP TABLE IF EXISTS `vista_permisos_estudiante`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_permisos_estudiante`  AS SELECT `e`.`id_estudiante` AS `id_estudiante`, `e`.`email` AS `estudiante_email`, `e`.`nombre_completo` AS `estudiante_nombre`, `e`.`id_departamento` AS `estudiante_departamento`, `c`.`id_categoria` AS `id_categoria`, `c`.`nombre_categoria` AS `nombre_categoria`, `c`.`codigo_categoria` AS `codigo_categoria`, `c`.`tipo_acceso` AS `tipo_acceso`, CASE WHEN `c`.`tipo_acceso` = 'Universal' THEN 'Acceso Universal' WHEN `c`.`id_departamento` = `e`.`id_departamento` THEN 'Acceso Departamental' ELSE 'Sin Acceso' END AS `tipo_permiso` FROM (`estudiantes` `e` join `categorias` `c`) WHERE `c`.`activo` = 1 AND (`c`.`tipo_acceso` = 'Universal' OR `c`.`id_departamento` = `e`.`id_departamento`) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `catalogo_articulos`
--
ALTER TABLE `catalogo_articulos`
  ADD PRIMARY KEY (`id_paquete`),
  ADD KEY `agregado_por` (`agregado_por`),
  ADD KEY `idx_categoria` (`categoria`),
  ADD KEY `fk_articulo_categoria` (`id_categoria`);

--
-- Indexes for table `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre_categoria` (`nombre_categoria`),
  ADD UNIQUE KEY `codigo_categoria` (`codigo_categoria`),
  ADD KEY `idx_tipo` (`tipo_acceso`),
  ADD KEY `idx_departamento` (`id_departamento`);

--
-- Indexes for table `constancias`
--
ALTER TABLE `constancias`
  ADD PRIMARY KEY (`id_constancia`),
  ADD KEY `idx_solicitud` (`id_solicitud`),
  ADD KEY `idx_estudiante` (`id_estudiante`),
  ADD KEY `idx_pdf_filename` (`pdf_filename`),
  ADD KEY `idx_fecha_generacion` (`fecha_generacion`),
  ADD KEY `idx_enviado` (`enviado_por_correo`);

--
-- Indexes for table `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id_departamento`),
  ADD UNIQUE KEY `nombre_departamento` (`nombre_departamento`),
  ADD UNIQUE KEY `codigo_departamento` (`codigo_departamento`),
  ADD KEY `idx_codigo` (`codigo_departamento`);

--
-- Indexes for table `donadores_detalle`
--
ALTER TABLE `donadores_detalle`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `idx_solicitud` (`id_solicitud`),
  ADD KEY `idx_numero_control` (`numero_control`);

--
-- Indexes for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_google_id` (`google_id`),
  ADD KEY `idx_departamento` (`id_departamento`);

--
-- Indexes for table `historial_articulos`
--
ALTER TABLE `historial_articulos`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_solicitud` (`id_solicitud`),
  ADD KEY `realizado_por` (`realizado_por`),
  ADD KEY `idx_fecha` (`fecha_accion`),
  ADD KEY `idx_paquete` (`id_paquete`);

--
-- Indexes for table `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `id_paquete` (`id_paquete`),
  ADD KEY `aprobado_por` (`aprobado_por`),
  ADD KEY `recibido_por` (`recibido_por`),
  ADD KEY `idx_estatus` (`estatus`),
  ADD KEY `idx_expiracion` (`fecha_expiracion`),
  ADD KEY `idx_carrera` (`carrera`),
  ADD KEY `idx_estudiante` (`id_estudiante`);

--
-- Indexes for table `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_google_id` (`google_id`),
  ADD KEY `idx_departamento` (`id_departamento`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `constancias`
--
ALTER TABLE `constancias`
  MODIFY `id_constancia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1001;

--
-- AUTO_INCREMENT for table `donadores_detalle`
--
ALTER TABLE `donadores_detalle`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `historial_articulos`
--
ALTER TABLE `historial_articulos`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id_solicitud` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `catalogo_articulos`
--
ALTER TABLE `catalogo_articulos`
  ADD CONSTRAINT `catalogo_articulos_ibfk_1` FOREIGN KEY (`agregado_por`) REFERENCES `usuarios_admin` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_articulo_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`);

--
-- Constraints for table `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id_departamento`) ON DELETE CASCADE;

--
-- Constraints for table `constancias`
--
ALTER TABLE `constancias`
  ADD CONSTRAINT `fk_constancia_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_constancia_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE;

--
-- Constraints for table `donadores_detalle`
--
ALTER TABLE `donadores_detalle`
  ADD CONSTRAINT `donadores_detalle_ibfk_1` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE;

--
-- Constraints for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `fk_estudiante_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id_departamento`) ON DELETE SET NULL;

--
-- Constraints for table `historial_articulos`
--
ALTER TABLE `historial_articulos`
  ADD CONSTRAINT `historial_articulos_ibfk_1` FOREIGN KEY (`id_paquete`) REFERENCES `catalogo_articulos` (`id_paquete`),
  ADD CONSTRAINT `historial_articulos_ibfk_2` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE SET NULL,
  ADD CONSTRAINT `historial_articulos_ibfk_3` FOREIGN KEY (`realizado_por`) REFERENCES `usuarios_admin` (`id_usuario`) ON DELETE SET NULL;

--
-- Constraints for table `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `solicitudes_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_ibfk_2` FOREIGN KEY (`id_paquete`) REFERENCES `catalogo_articulos` (`id_paquete`),
  ADD CONSTRAINT `solicitudes_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios_admin` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_ibfk_4` FOREIGN KEY (`recibido_por`) REFERENCES `usuarios_admin` (`id_usuario`) ON DELETE SET NULL;

--
-- Constraints for table `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  ADD CONSTRAINT `fk_usuario_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id_departamento`) ON DELETE SET NULL;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `evento_expirar_reservas` ON SCHEDULE EVERY 1 HOUR STARTS '2026-01-30 16:43:47' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE solicitudes
    SET estatus = 'Expirado'
    WHERE estatus = 'Reservado'
    AND fecha_expiracion < NOW();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
