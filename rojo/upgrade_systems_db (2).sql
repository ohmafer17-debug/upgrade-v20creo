-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 07-07-2026 a las 03:26:31
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `upgrade_systems_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admin_ups`
--

CREATE TABLE `admin_ups` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_secundario` varchar(100) DEFAULT NULL,
  `telefono_principal` varchar(20) DEFAULT NULL,
  `telefono_secundario` varchar(20) DEFAULT NULL,
  `pass` varchar(100) NOT NULL,
  `rol` varchar(50) NOT NULL DEFAULT 'Usuario Estándar',
  `estatus` varchar(20) DEFAULT 'Activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `admin_ups`
--

INSERT INTO `admin_ups` (`id`, `nombre`, `email`, `email_secundario`, `telefono_principal`, `telefono_secundario`, `pass`, `rol`, `estatus`, `fecha_registro`) VALUES
(1, 'Mafer Administradora', 'ohmafer.17@gmail.com', NULL, NULL, NULL, '$2y$12$IBNOsC2xCxtcotNCauRxk.NJtAvN5mGK.ies0/Do7R61u2lGhTHx6', 'Administrador', 'Activo', '2026-06-24 23:12:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_pc`
--

CREATE TABLE `documentos_pc` (
  `id` int(11) NOT NULL,
  `empresa_cod` varchar(50) NOT NULL,
  `tipo_doc` varchar(100) NOT NULL,
  `nombre_personalizado` varchar(150) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `fecha_subida_sistema` date DEFAULT curdate(),
  `estatus` int(11) DEFAULT 1,
  `nombre_archivo_fisico` varchar(255) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `subido_por` varchar(255) DEFAULT NULL,
  `actualizado_por` varchar(255) DEFAULT NULL,
  `visto_por` varchar(255) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documentos_pc`
--

INSERT INTO `documentos_pc` (`id`, `empresa_cod`, `tipo_doc`, `nombre_personalizado`, `fecha_vencimiento`, `fecha_subida_sistema`, `estatus`, `nombre_archivo_fisico`, `fecha_subida`, `subido_por`, `actualizado_por`, `visto_por`, `motivo`) VALUES
(1, 'CONS-01', 'OTRO', 'LICENCIA_prueba [Reg: 2026-06-26 03:18:18]', '2027-09-24', '2026-06-26', 1, '23cd65cad4391703cc8f698e3e9d2a4c.png', '2026-06-26 03:19:02', 'CONSULTORIA PC', NULL, 'Fernanda Oliva', NULL),
(2, 'CONS-1bf5', 'Programa Interno de PC', 'ejemplo [Reg: 2026-06-26 05:46:47]', '2026-07-09', '2026-06-26', 1, '06ddb0d1e26bc0ff21dacb93e00fa49b.png', '2026-06-26 05:47:11', 'Fernanda Oliva', NULL, 'Fernanda Oliva', NULL),
(8, 'CONS-02', 'Programa Interno de PC', 'test01| [Reg: 2026-07-06 00:02:13]', '2027-08-15', '2026-07-06', 1, '4e1307e118db4225c76c1a7a4a151c3e.png', '2026-07-06 04:48:24', 'Consultoría Alfa', 'Consultoría Alfa', 'Consultoría Alfa', 'lo usare'),
(9, 'CONS-02', 'dictamen', 'test [Reg: 2026-07-06 00:14:21]', '2025-09-06', '2026-07-06', 1, '1610e6f368a65d3a5a9058cd97efb8ce.png', '2026-07-06 06:14:21', 'Consultoría Alfa', NULL, NULL, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas_clientes`
--

CREATE TABLE `empresas_clientes` (
  `id` int(11) NOT NULL,
  `cod` varchar(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_adicional` varchar(100) DEFAULT NULL,
  `telefono_principal` varchar(20) DEFAULT NULL,
  `telefono_adicional` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `coordenadas` varchar(100) DEFAULT NULL,
  `pass` varchar(255) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `rol` varchar(100) DEFAULT 'Socio Fundador',
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empresas_clientes`
--

INSERT INTO `empresas_clientes` (`id`, `cod`, `nombre`, `email`, `email_adicional`, `telefono_principal`, `telefono_adicional`, `direccion`, `coordenadas`, `pass`, `staff_id`, `activo`, `rol`, `logo`) VALUES
(1, 'GIRD-01', 'GIRD CONSULTORIA', 'rocadi@gmail.com', '', '7224612052', '', 'Alamo norte 113, 0', '19.2762066,-99.6675493', '$2y$10$M2nBPJO8m9dfSccjbaePFODhp1TEacpuFOVY2lRoHhxMnVr4GykOm', NULL, 1, 'Consultor', NULL),
(2, 'CONS-01', 'CONSULTORIA PC', 'rocalo@gmail.com', '', '7224612052', '', 'Alamo norte 113, 0', '19.2762066,-99.6675493', '$2y$10$rTSUEyLP6vMJqiIUpQ3fvueCfIpbBndTzg5YNmdC2MrWvUEUjwree', NULL, 1, 'Consultor', NULL),
(3, 'CONS-2b63', 'Fernando Perez', 'fernando@empresa.com', 'fernando@personal.com', '1234567890', '7221472589', NULL, NULL, '$2y$10$iiGDQHd4ZRCTvcZEw8kyqOXm6mPA2AEOGtnGQ5rCrwgzmoiAhN8su', NULL, 1, 'Tipo 2', NULL),
(4, 'CONS-1bf5', 'Fernanda Oliva', 'ferss@principal.com', 'ferss@personal.com', '7894556123', '7418529663', NULL, NULL, '$2y$10$elYI32m6FATmQ/tNxqRDYefKV8B.W1uO3rwPLS5fcvBAukPBSni.C', NULL, 1, 'Responsable Nacional', NULL),
(5, 'CONS-02', 'Consultoría Alfa', 'alvaro@principal.com', 'alvaro@secundario.com', '1478523690', '1478523690', 'Alamo norte 113, 0', '19.3061712,-99.5949204', '$2y$10$mJYSga.llC5UzIrt7tZYHOke6wrfX0ugohkpc6ZpnnhI9uzMofeQe', NULL, 1, 'Consultor', '1925fedf40427fbd5df4b4b362d13357.jpg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_documentos`
--

CREATE TABLE `historial_documentos` (
  `id` int(11) NOT NULL,
  `documento_id` int(11) NOT NULL,
  `empresa_cod` varchar(50) NOT NULL,
  `tipo_doc` varchar(100) NOT NULL,
  `nombre_personalizado` varchar(150) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `nombre_archivo_fisico` varchar(255) NOT NULL,
  `subido_por` varchar(255) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_documentos`
--

INSERT INTO `historial_documentos` (`id`, `documento_id`, `empresa_cod`, `tipo_doc`, `nombre_personalizado`, `fecha_vencimiento`, `nombre_archivo_fisico`, `subido_por`, `motivo`, `fecha_modificacion`) VALUES
(1, 8, 'CONS-02', 'Programa Interno de PC', 'test [Reg: 2026-07-06 04:45:15]', '2025-08-15', 'aa189244ed9b27c01b8d41febcd216ef.png', NULL, 'lo usare', '2026-07-06 06:02:13');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admin_ups`
--
ALTER TABLE `admin_ups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `documentos_pc`
--
ALTER TABLE `documentos_pc`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `empresas_clientes`
--
ALTER TABLE `empresas_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cod` (`cod`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indices de la tabla `historial_documentos`
--
ALTER TABLE `historial_documentos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admin_ups`
--
ALTER TABLE `admin_ups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `documentos_pc`
--
ALTER TABLE `documentos_pc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `empresas_clientes`
--
ALTER TABLE `empresas_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `historial_documentos`
--
ALTER TABLE `historial_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
