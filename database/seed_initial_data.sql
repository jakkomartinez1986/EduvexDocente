-- ============================================================
-- SQL de datos iniciales para EduVex Docente
-- Generado a partir de DataPrincipalSeeder + PermissionsSeeder + DatabaseSeeder
-- Base de datos: PostgreSQL
-- Anio escolar: 2026-2027
-- ============================================================
-- INSTRUCCIONES:
-- 1. Ejecutar este archivo despues de correr las migraciones
-- 2. Copiar y pegar cada bloque en una consola SQL, o ejecutar el archivo completo
-- 3. Los IDs son explicitos para facilitar las referencias
-- ============================================================

BEGIN;

-- ============================================================
-- 1. PERMISOS (260 registros: 52 modelos x 5 operaciones)
-- ============================================================
-- Modelo: User
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(1, 'ver-user', 'Ver User', 'User', 'web', NOW(), NOW()),
(2, 'crear-user', 'Crear User', 'User', 'web', NOW(), NOW()),
(3, 'editar-user', 'Editar User', 'User', 'web', NOW(), NOW()),
(4, 'borrar-user', 'Borrar User', 'User', 'web', NOW(), NOW()),
(5, 'actualizar-estado-user', 'Actualizar Estado User', 'User', 'web', NOW(), NOW());

-- Modelo: Teacher
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(6, 'ver-teacher', 'Ver Teacher', 'Teacher', 'web', NOW(), NOW()),
(7, 'crear-teacher', 'Crear Teacher', 'Teacher', 'web', NOW(), NOW()),
(8, 'editar-teacher', 'Editar Teacher', 'Teacher', 'web', NOW(), NOW()),
(9, 'borrar-teacher', 'Borrar Teacher', 'Teacher', 'web', NOW(), NOW()),
(10, 'actualizar-estado-teacher', 'Actualizar Estado Teacher', 'Teacher', 'web', NOW(), NOW());

-- Modelo: Student
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(11, 'ver-student', 'Ver Student', 'Student', 'web', NOW(), NOW()),
(12, 'crear-student', 'Crear Student', 'Student', 'web', NOW(), NOW()),
(13, 'editar-student', 'Editar Student', 'Student', 'web', NOW(), NOW()),
(14, 'borrar-student', 'Borrar Student', 'Student', 'web', NOW(), NOW()),
(15, 'actualizar-estado-student', 'Actualizar Estado Student', 'Student', 'web', NOW(), NOW());

-- Modelo: Representative
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(16, 'ver-representative', 'Ver Representative', 'Representative', 'web', NOW(), NOW()),
(17, 'crear-representative', 'Crear Representative', 'Representative', 'web', NOW(), NOW()),
(18, 'editar-representative', 'Editar Representative', 'Representative', 'web', NOW(), NOW()),
(19, 'borrar-representative', 'Borrar Representative', 'Representative', 'web', NOW(), NOW()),
(20, 'actualizar-estado-representative', 'Actualizar Estado Representative', 'Representative', 'web', NOW(), NOW());

-- Modelo: Role
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(21, 'ver-role', 'Ver Role', 'Role', 'web', NOW(), NOW()),
(22, 'crear-role', 'Crear Role', 'Role', 'web', NOW(), NOW()),
(23, 'editar-role', 'Editar Role', 'Role', 'web', NOW(), NOW()),
(24, 'borrar-role', 'Borrar Role', 'Role', 'web', NOW(), NOW()),
(25, 'actualizar-estado-role', 'Actualizar Estado Role', 'Role', 'web', NOW(), NOW());

-- Modelo: Permission
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(26, 'ver-permission', 'Ver Permission', 'Permission', 'web', NOW(), NOW()),
(27, 'crear-permission', 'Crear Permission', 'Permission', 'web', NOW(), NOW()),
(28, 'editar-permission', 'Editar Permission', 'Permission', 'web', NOW(), NOW()),
(29, 'borrar-permission', 'Borrar Permission', 'Permission', 'web', NOW(), NOW()),
(30, 'actualizar-estado-permission', 'Actualizar Estado Permission', 'Permission', 'web', NOW(), NOW());

-- Modelo: School
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(31, 'ver-school', 'Ver School', 'School', 'web', NOW(), NOW()),
(32, 'crear-school', 'Crear School', 'School', 'web', NOW(), NOW()),
(33, 'editar-school', 'Editar School', 'School', 'web', NOW(), NOW()),
(34, 'borrar-school', 'Borrar School', 'School', 'web', NOW(), NOW()),
(35, 'actualizar-estado-school', 'Actualizar Estado School', 'School', 'web', NOW(), NOW());

-- Modelo: Shift
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(36, 'ver-shift', 'Ver Shift', 'Shift', 'web', NOW(), NOW()),
(37, 'crear-shift', 'Crear Shift', 'Shift', 'web', NOW(), NOW()),
(38, 'editar-shift', 'Editar Shift', 'Shift', 'web', NOW(), NOW()),
(39, 'borrar-shift', 'Borrar Shift', 'Shift', 'web', NOW(), NOW()),
(40, 'actualizar-estado-shift', 'Actualizar Estado Shift', 'Shift', 'web', NOW(), NOW());

-- Modelo: Nivel
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(41, 'ver-nivel', 'Ver Nivel', 'Nivel', 'web', NOW(), NOW()),
(42, 'crear-nivel', 'Crear Nivel', 'Nivel', 'web', NOW(), NOW()),
(43, 'editar-nivel', 'Editar Nivel', 'Nivel', 'web', NOW(), NOW()),
(44, 'borrar-nivel', 'Borrar Nivel', 'Nivel', 'web', NOW(), NOW()),
(45, 'actualizar-estado-nivel', 'Actualizar Estado Nivel', 'Nivel', 'web', NOW(), NOW());

-- Modelo: Grade
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(46, 'ver-grade', 'Ver Grade', 'Grade', 'web', NOW(), NOW()),
(47, 'crear-grade', 'Crear Grade', 'Grade', 'web', NOW(), NOW()),
(48, 'editar-grade', 'Editar Grade', 'Grade', 'web', NOW(), NOW()),
(49, 'borrar-grade', 'Borrar Grade', 'Grade', 'web', NOW(), NOW()),
(50, 'actualizar-estado-grade', 'Actualizar Estado Grade', 'Grade', 'web', NOW(), NOW());

-- Modelo: Parallel
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(51, 'ver-parallel', 'Ver Parallel', 'Parallel', 'web', NOW(), NOW()),
(52, 'crear-parallel', 'Crear Parallel', 'Parallel', 'web', NOW(), NOW()),
(53, 'editar-parallel', 'Editar Parallel', 'Parallel', 'web', NOW(), NOW()),
(54, 'borrar-parallel', 'Borrar Parallel', 'Parallel', 'web', NOW(), NOW()),
(55, 'actualizar-estado-parallel', 'Actualizar Estado Parallel', 'Parallel', 'web', NOW(), NOW());

-- Modelo: Classroom
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(56, 'ver-classroom', 'Ver Classroom', 'Classroom', 'web', NOW(), NOW()),
(57, 'crear-classroom', 'Crear Classroom', 'Classroom', 'web', NOW(), NOW()),
(58, 'editar-classroom', 'Editar Classroom', 'Classroom', 'web', NOW(), NOW()),
(59, 'borrar-classroom', 'Borrar Classroom', 'Classroom', 'web', NOW(), NOW()),
(60, 'actualizar-estado-classroom', 'Actualizar Estado Classroom', 'Classroom', 'web', NOW(), NOW());

-- Modelo: Area
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(61, 'ver-area', 'Ver Area', 'Area', 'web', NOW(), NOW()),
(62, 'crear-area', 'Crear Area', 'Area', 'web', NOW(), NOW()),
(63, 'editar-area', 'Editar Area', 'Area', 'web', NOW(), NOW()),
(64, 'borrar-area', 'Borrar Area', 'Area', 'web', NOW(), NOW()),
(65, 'actualizar-estado-area', 'Actualizar Estado Area', 'Area', 'web', NOW(), NOW());

-- Modelo: Subject
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(66, 'ver-subject', 'Ver Subject', 'Subject', 'web', NOW(), NOW()),
(67, 'crear-subject', 'Crear Subject', 'Subject', 'web', NOW(), NOW()),
(68, 'editar-subject', 'Editar Subject', 'Subject', 'web', NOW(), NOW()),
(69, 'borrar-subject', 'Borrar Subject', 'Subject', 'web', NOW(), NOW()),
(70, 'actualizar-estado-subject', 'Actualizar Estado Subject', 'Subject', 'web', NOW(), NOW());

-- Modelo: ScolarYear
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(71, 'ver-scolaryear', 'Ver Scolaryear', 'ScolarYear', 'web', NOW(), NOW()),
(72, 'crear-scolaryear', 'Crear Scolaryear', 'ScolarYear', 'web', NOW(), NOW()),
(73, 'editar-scolaryear', 'Editar Scolaryear', 'ScolarYear', 'web', NOW(), NOW()),
(74, 'borrar-scolaryear', 'Borrar Scolaryear', 'ScolarYear', 'web', NOW(), NOW()),
(75, 'actualizar-estado-scolaryear', 'Actualizar Estado Scolaryear', 'ScolarYear', 'web', NOW(), NOW());

-- Modelo: AcademicPeriod
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(76, 'ver-academicperiod', 'Ver Academicperiod', 'AcademicPeriod', 'web', NOW(), NOW()),
(77, 'crear-academicperiod', 'Crear Academicperiod', 'AcademicPeriod', 'web', NOW(), NOW()),
(78, 'editar-academicperiod', 'Editar Academicperiod', 'AcademicPeriod', 'web', NOW(), NOW()),
(79, 'borrar-academicperiod', 'Borrar Academicperiod', 'AcademicPeriod', 'web', NOW(), NOW()),
(80, 'actualizar-estado-academicperiod', 'Actualizar Estado Academicperiod', 'AcademicPeriod', 'web', NOW(), NOW());

-- Modelo: GradingScheme
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(81, 'ver-gradingscheme', 'Ver Gradingscheme', 'GradingScheme', 'web', NOW(), NOW()),
(82, 'crear-gradingscheme', 'Crear Gradingscheme', 'GradingScheme', 'web', NOW(), NOW()),
(83, 'editar-gradingscheme', 'Editar Gradingscheme', 'GradingScheme', 'web', NOW(), NOW()),
(84, 'borrar-gradingscheme', 'Borrar Gradingscheme', 'GradingScheme', 'web', NOW(), NOW()),
(85, 'actualizar-estado-gradingscheme', 'Actualizar Estado Gradingscheme', 'GradingScheme', 'web', NOW(), NOW());

-- Modelo: CalendarDay
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(86, 'ver-calendarday', 'Ver Calendarday', 'CalendarDay', 'web', NOW(), NOW()),
(87, 'crear-calendarday', 'Crear Calendarday', 'CalendarDay', 'web', NOW(), NOW()),
(88, 'editar-calendarday', 'Editar Calendarday', 'CalendarDay', 'web', NOW(), NOW()),
(89, 'borrar-calendarday', 'Borrar Calendarday', 'CalendarDay', 'web', NOW(), NOW()),
(90, 'actualizar-estado-calendarday', 'Actualizar Estado Calendarday', 'CalendarDay', 'web', NOW(), NOW());

-- Modelo: ChannelConfiguration
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(91, 'ver-channelconfiguration', 'Ver Channelconfiguration', 'ChannelConfiguration', 'web', NOW(), NOW()),
(92, 'crear-channelconfiguration', 'Crear Channelconfiguration', 'ChannelConfiguration', 'web', NOW(), NOW()),
(93, 'editar-channelconfiguration', 'Editar Channelconfiguration', 'ChannelConfiguration', 'web', NOW(), NOW()),
(94, 'borrar-channelconfiguration', 'Borrar Channelconfiguration', 'ChannelConfiguration', 'web', NOW(), NOW()),
(95, 'actualizar-estado-channelconfiguration', 'Actualizar Estado Channelconfiguration', 'ChannelConfiguration', 'web', NOW(), NOW());

-- Modelo: StudentEnrollment
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(96, 'ver-studentenrollment', 'Ver Studentenrollment', 'StudentEnrollment', 'web', NOW(), NOW()),
(97, 'crear-studentenrollment', 'Crear Studentenrollment', 'StudentEnrollment', 'web', NOW(), NOW()),
(98, 'editar-studentenrollment', 'Editar Studentenrollment', 'StudentEnrollment', 'web', NOW(), NOW()),
(99, 'borrar-studentenrollment', 'Borrar Studentenrollment', 'StudentEnrollment', 'web', NOW(), NOW()),
(100, 'actualizar-estado-studentenrollment', 'Actualizar Estado Studentenrollment', 'StudentEnrollment', 'web', NOW(), NOW());

-- Modelo: ClassSchedule
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(101, 'ver-classschedule', 'Ver Classschedule', 'ClassSchedule', 'web', NOW(), NOW()),
(102, 'crear-classschedule', 'Crear Classschedule', 'ClassSchedule', 'web', NOW(), NOW()),
(103, 'editar-classschedule', 'Editar Classschedule', 'ClassSchedule', 'web', NOW(), NOW()),
(104, 'borrar-classschedule', 'Borrar Classschedule', 'ClassSchedule', 'web', NOW(), NOW()),
(105, 'actualizar-estado-classschedule', 'Actualizar Estado Classschedule', 'ClassSchedule', 'web', NOW(), NOW());

-- Modelo: Attendance
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(106, 'ver-attendance', 'Ver Attendance', 'Attendance', 'web', NOW(), NOW()),
(107, 'crear-attendance', 'Crear Attendance', 'Attendance', 'web', NOW(), NOW()),
(108, 'editar-attendance', 'Editar Attendance', 'Attendance', 'web', NOW(), NOW()),
(109, 'borrar-attendance', 'Borrar Attendance', 'Attendance', 'web', NOW(), NOW()),
(110, 'actualizar-estado-attendance', 'Actualizar Estado Attendance', 'Attendance', 'web', NOW(), NOW());

-- Modelo: AttendanceSummary
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(111, 'ver-attendancesummary', 'Ver Attendancesummary', 'AttendanceSummary', 'web', NOW(), NOW()),
(112, 'crear-attendancesummary', 'Crear Attendancesummary', 'AttendanceSummary', 'web', NOW(), NOW()),
(113, 'editar-attendancesummary', 'Editar Attendancesummary', 'AttendanceSummary', 'web', NOW(), NOW()),
(114, 'borrar-attendancesummary', 'Borrar Attendancesummary', 'AttendanceSummary', 'web', NOW(), NOW()),
(115, 'actualizar-estado-attendancesummary', 'Actualizar Estado Attendancesummary', 'AttendanceSummary', 'web', NOW(), NOW());

-- Modelo: ClassObservation
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(116, 'ver-classobservation', 'Ver Classobservation', 'ClassObservation', 'web', NOW(), NOW()),
(117, 'crear-classobservation', 'Crear Classobservation', 'ClassObservation', 'web', NOW(), NOW()),
(118, 'editar-classobservation', 'Editar Classobservation', 'ClassObservation', 'web', NOW(), NOW()),
(119, 'borrar-classobservation', 'Borrar Classobservation', 'ClassObservation', 'web', NOW(), NOW()),
(120, 'actualizar-estado-classobservation', 'Actualizar Estado Classobservation', 'ClassObservation', 'web', NOW(), NOW());

-- Modelo: AcademicRecord
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(121, 'ver-academicrecord', 'Ver Academicrecord', 'AcademicRecord', 'web', NOW(), NOW()),
(122, 'crear-academicrecord', 'Crear Academicrecord', 'AcademicRecord', 'web', NOW(), NOW()),
(123, 'editar-academicrecord', 'Editar Academicrecord', 'AcademicRecord', 'web', NOW(), NOW()),
(124, 'borrar-academicrecord', 'Borrar Academicrecord', 'AcademicRecord', 'web', NOW(), NOW()),
(125, 'actualizar-estado-academicrecord', 'Actualizar Estado Academicrecord', 'AcademicRecord', 'web', NOW(), NOW());

-- Modelo: AcademicNotification
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(126, 'ver-academicnotification', 'Ver Academicnotification', 'AcademicNotification', 'web', NOW(), NOW()),
(127, 'crear-academicnotification', 'Crear Academicnotification', 'AcademicNotification', 'web', NOW(), NOW()),
(128, 'editar-academicnotification', 'Editar Academicnotification', 'AcademicNotification', 'web', NOW(), NOW()),
(129, 'borrar-academicnotification', 'Borrar Academicnotification', 'AcademicNotification', 'web', NOW(), NOW()),
(130, 'actualizar-estado-academicnotification', 'Actualizar Estado Academicnotification', 'AcademicNotification', 'web', NOW(), NOW());

-- Modelo: HomeworkPending
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(131, 'ver-homeworkpending', 'Ver Homeworkpending', 'HomeworkPending', 'web', NOW(), NOW()),
(132, 'crear-homeworkpending', 'Crear Homeworkpending', 'HomeworkPending', 'web', NOW(), NOW()),
(133, 'editar-homeworkpending', 'Editar Homeworkpending', 'HomeworkPending', 'web', NOW(), NOW()),
(134, 'borrar-homeworkpending', 'Borrar Homeworkpending', 'HomeworkPending', 'web', NOW(), NOW()),
(135, 'actualizar-estado-homeworkpending', 'Actualizar Estado Homeworkpending', 'HomeworkPending', 'web', NOW(), NOW());

-- Modelo: AssessmentBlock
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(136, 'ver-assessmentblock', 'Ver Assessmentblock', 'AssessmentBlock', 'web', NOW(), NOW()),
(137, 'crear-assessmentblock', 'Crear Assessmentblock', 'AssessmentBlock', 'web', NOW(), NOW()),
(138, 'editar-assessmentblock', 'Editar Assessmentblock', 'AssessmentBlock', 'web', NOW(), NOW()),
(139, 'borrar-assessmentblock', 'Borrar Assessmentblock', 'AssessmentBlock', 'web', NOW(), NOW()),
(140, 'actualizar-estado-assessmentblock', 'Actualizar Estado Assessmentblock', 'AssessmentBlock', 'web', NOW(), NOW());

-- Modelo: Activity
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(141, 'ver-activity', 'Ver Activity', 'Activity', 'web', NOW(), NOW()),
(142, 'crear-activity', 'Crear Activity', 'Activity', 'web', NOW(), NOW()),
(143, 'editar-activity', 'Editar Activity', 'Activity', 'web', NOW(), NOW()),
(144, 'borrar-activity', 'Borrar Activity', 'Activity', 'web', NOW(), NOW()),
(145, 'actualizar-estado-activity', 'Actualizar Estado Activity', 'Activity', 'web', NOW(), NOW());

-- Modelo: ActivityGrade
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(146, 'ver-activitygrade', 'Ver Activitygrade', 'ActivityGrade', 'web', NOW(), NOW()),
(147, 'crear-activitygrade', 'Crear Activitygrade', 'ActivityGrade', 'web', NOW(), NOW()),
(148, 'editar-activitygrade', 'Editar Activitygrade', 'ActivityGrade', 'web', NOW(), NOW()),
(149, 'borrar-activitygrade', 'Borrar Activitygrade', 'ActivityGrade', 'web', NOW(), NOW()),
(150, 'actualizar-estado-activitygrade', 'Actualizar Estado Activitygrade', 'ActivityGrade', 'web', NOW(), NOW());

-- Modelo: ActivityRecovery
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(151, 'ver-activityrecovery', 'Ver Activityrecovery', 'ActivityRecovery', 'web', NOW(), NOW()),
(152, 'crear-activityrecovery', 'Crear Activityrecovery', 'ActivityRecovery', 'web', NOW(), NOW()),
(153, 'editar-activityrecovery', 'Editar Activityrecovery', 'ActivityRecovery', 'web', NOW(), NOW()),
(154, 'borrar-activityrecovery', 'Borrar Activityrecovery', 'ActivityRecovery', 'web', NOW(), NOW()),
(155, 'actualizar-estado-activityrecovery', 'Actualizar Estado Activityrecovery', 'ActivityRecovery', 'web', NOW(), NOW());

-- Modelo: StudentExam
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(156, 'ver-studentexam', 'Ver Studentexam', 'StudentExam', 'web', NOW(), NOW()),
(157, 'crear-studentexam', 'Crear Studentexam', 'StudentExam', 'web', NOW(), NOW()),
(158, 'editar-studentexam', 'Editar Studentexam', 'StudentExam', 'web', NOW(), NOW()),
(159, 'borrar-studentexam', 'Borrar Studentexam', 'StudentExam', 'web', NOW(), NOW()),
(160, 'actualizar-estado-studentexam', 'Actualizar Estado Studentexam', 'StudentExam', 'web', NOW(), NOW());

-- Modelo: StudentProject
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(161, 'ver-studentproject', 'Ver Studentproject', 'StudentProject', 'web', NOW(), NOW()),
(162, 'crear-studentproject', 'Crear Studentproject', 'StudentProject', 'web', NOW(), NOW()),
(163, 'editar-studentproject', 'Editar Studentproject', 'StudentProject', 'web', NOW(), NOW()),
(164, 'borrar-studentproject', 'Borrar Studentproject', 'StudentProject', 'web', NOW(), NOW()),
(165, 'actualizar-estado-studentproject', 'Actualizar Estado Studentproject', 'StudentProject', 'web', NOW(), NOW());

-- Modelo: SupplementaryExam
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(166, 'ver-supplementaryexam', 'Ver Supplementaryexam', 'SupplementaryExam', 'web', NOW(), NOW()),
(167, 'crear-supplementaryexam', 'Crear Supplementaryexam', 'SupplementaryExam', 'web', NOW(), NOW()),
(168, 'editar-supplementaryexam', 'Editar Supplementaryexam', 'SupplementaryExam', 'web', NOW(), NOW()),
(169, 'borrar-supplementaryexam', 'Borrar Supplementaryexam', 'SupplementaryExam', 'web', NOW(), NOW()),
(170, 'actualizar-estado-supplementaryexam', 'Actualizar Estado Supplementaryexam', 'SupplementaryExam', 'web', NOW(), NOW());

-- Modelo: ExamRecovery
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(171, 'ver-examrecovery', 'Ver Examrecovery', 'ExamRecovery', 'web', NOW(), NOW()),
(172, 'crear-examrecovery', 'Crear Examrecovery', 'ExamRecovery', 'web', NOW(), NOW()),
(173, 'editar-examrecovery', 'Editar Examrecovery', 'ExamRecovery', 'web', NOW(), NOW()),
(174, 'borrar-examrecovery', 'Borrar Examrecovery', 'ExamRecovery', 'web', NOW(), NOW()),
(175, 'actualizar-estado-examrecovery', 'Actualizar Estado Examrecovery', 'ExamRecovery', 'web', NOW(), NOW());

-- Modelo: AcademicReinforcement
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(176, 'ver-academicreinforcement', 'Ver Academicreinforcement', 'AcademicReinforcement', 'web', NOW(), NOW()),
(177, 'crear-academicreinforcement', 'Crear Academicreinforcement', 'AcademicReinforcement', 'web', NOW(), NOW()),
(178, 'editar-academicreinforcement', 'Editar Academicreinforcement', 'AcademicReinforcement', 'web', NOW(), NOW()),
(179, 'borrar-academicreinforcement', 'Borrar Academicreinforcement', 'AcademicReinforcement', 'web', NOW(), NOW()),
(180, 'actualizar-estado-academicreinforcement', 'Actualizar Estado Academicreinforcement', 'AcademicReinforcement', 'web', NOW(), NOW());

-- Modelo: GraduationExam
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(181, 'ver-graduationexam', 'Ver Graduationexam', 'GraduationExam', 'web', NOW(), NOW()),
(182, 'crear-graduationexam', 'Crear Graduationexam', 'GraduationExam', 'web', NOW(), NOW()),
(183, 'editar-graduationexam', 'Editar Graduationexam', 'GraduationExam', 'web', NOW(), NOW()),
(184, 'borrar-graduationexam', 'Borrar Graduationexam', 'GraduationExam', 'web', NOW(), NOW()),
(185, 'actualizar-estado-graduationexam', 'Actualizar Estado Graduationexam', 'GraduationExam', 'web', NOW(), NOW());

-- Modelo: LearningDomain
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(186, 'ver-learningdomain', 'Ver Learningdomain', 'LearningDomain', 'web', NOW(), NOW()),
(187, 'crear-learningdomain', 'Crear Learningdomain', 'LearningDomain', 'web', NOW(), NOW()),
(188, 'editar-learningdomain', 'Editar Learningdomain', 'LearningDomain', 'web', NOW(), NOW()),
(189, 'borrar-learningdomain', 'Borrar Learningdomain', 'LearningDomain', 'web', NOW(), NOW()),
(190, 'actualizar-estado-learningdomain', 'Actualizar Estado Learningdomain', 'LearningDomain', 'web', NOW(), NOW());

-- Modelo: LearningDomainAssessment
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(191, 'ver-learningdomainassessment', 'Ver Learningdomainassessment', 'LearningDomainAssessment', 'web', NOW(), NOW()),
(192, 'crear-learningdomainassessment', 'Crear Learningdomainassessment', 'LearningDomainAssessment', 'web', NOW(), NOW()),
(193, 'editar-learningdomainassessment', 'Editar Learningdomainassessment', 'LearningDomainAssessment', 'web', NOW(), NOW()),
(194, 'borrar-learningdomainassessment', 'Borrar Learningdomainassessment', 'LearningDomainAssessment', 'web', NOW(), NOW()),
(195, 'actualizar-estado-learningdomainassessment', 'Actualizar Estado Learningdomainassessment', 'LearningDomainAssessment', 'web', NOW(), NOW());

-- Modelo: LearningDomainEvaluationScale
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(196, 'ver-learningdomainevaluationscale', 'Ver Learningdomainevaluationscale', 'LearningDomainEvaluationScale', 'web', NOW(), NOW()),
(197, 'crear-learningdomainevaluationscale', 'Crear Learningdomainevaluationscale', 'LearningDomainEvaluationScale', 'web', NOW(), NOW()),
(198, 'editar-learningdomainevaluationscale', 'Editar Learningdomainevaluationscale', 'LearningDomainEvaluationScale', 'web', NOW(), NOW()),
(199, 'borrar-learningdomainevaluationscale', 'Borrar Learningdomainevaluationscale', 'LearningDomainEvaluationScale', 'web', NOW(), NOW()),
(200, 'actualizar-estado-learningdomainevaluationscale', 'Actualizar Estado Learningdomainevaluationscale', 'LearningDomainEvaluationScale', 'web', NOW(), NOW());

-- Modelo: LearningDomainIndicator
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(201, 'ver-learningdomainindicator', 'Ver Learningdomainindicator', 'LearningDomainIndicator', 'web', NOW(), NOW()),
(202, 'crear-learningdomainindicator', 'Crear Learningdomainindicator', 'LearningDomainIndicator', 'web', NOW(), NOW()),
(203, 'editar-learningdomainindicator', 'Editar Learningdomainindicator', 'LearningDomainIndicator', 'web', NOW(), NOW()),
(204, 'borrar-learningdomainindicator', 'Borrar Learningdomainindicator', 'LearningDomainIndicator', 'web', NOW(), NOW()),
(205, 'actualizar-estado-learningdomainindicator', 'Actualizar Estado Learningdomainindicator', 'LearningDomainIndicator', 'web', NOW(), NOW());

-- Modelo: CareerGuidance
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(206, 'ver-careerguidance', 'Ver Careerguidance', 'CareerGuidance', 'web', NOW(), NOW()),
(207, 'crear-careerguidance', 'Crear Careerguidance', 'CareerGuidance', 'web', NOW(), NOW()),
(208, 'editar-careerguidance', 'Editar Careerguidance', 'CareerGuidance', 'web', NOW(), NOW()),
(209, 'borrar-careerguidance', 'Borrar Careerguidance', 'CareerGuidance', 'web', NOW(), NOW()),
(210, 'actualizar-estado-careerguidance', 'Actualizar Estado Careerguidance', 'CareerGuidance', 'web', NOW(), NOW());

-- Modelo: CareerGuidanceIndicator
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(211, 'ver-careerguidanceindicator', 'Ver Careerguidanceindicator', 'CareerGuidanceIndicator', 'web', NOW(), NOW()),
(212, 'crear-careerguidanceindicator', 'Crear Careerguidanceindicator', 'CareerGuidanceIndicator', 'web', NOW(), NOW()),
(213, 'editar-careerguidanceindicator', 'Editar Careerguidanceindicator', 'CareerGuidanceIndicator', 'web', NOW(), NOW()),
(214, 'borrar-careerguidanceindicator', 'Borrar Careerguidanceindicator', 'CareerGuidanceIndicator', 'web', NOW(), NOW()),
(215, 'actualizar-estado-careerguidanceindicator', 'Actualizar Estado Careerguidanceindicator', 'CareerGuidanceIndicator', 'web', NOW(), NOW());

-- Modelo: ReadingPromotion
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(216, 'ver-readingpromotion', 'Ver Readingpromotion', 'ReadingPromotion', 'web', NOW(), NOW()),
(217, 'crear-readingpromotion', 'Crear Readingpromotion', 'ReadingPromotion', 'web', NOW(), NOW()),
(218, 'editar-readingpromotion', 'Editar Readingpromotion', 'ReadingPromotion', 'web', NOW(), NOW()),
(219, 'borrar-readingpromotion', 'Borrar Readingpromotion', 'ReadingPromotion', 'web', NOW(), NOW()),
(220, 'actualizar-estado-readingpromotion', 'Actualizar Estado Readingpromotion', 'ReadingPromotion', 'web', NOW(), NOW());

-- Modelo: ReadingPromotionIndicator
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(221, 'ver-readingpromotionindicator', 'Ver Readingpromotionindicator', 'ReadingPromotionIndicator', 'web', NOW(), NOW()),
(222, 'crear-readingpromotionindicator', 'Crear Readingpromotionindicator', 'ReadingPromotionIndicator', 'web', NOW(), NOW()),
(223, 'editar-readingpromotionindicator', 'Editar Readingpromotionindicator', 'ReadingPromotionIndicator', 'web', NOW(), NOW()),
(224, 'borrar-readingpromotionindicator', 'Borrar Readingpromotionindicator', 'ReadingPromotionIndicator', 'web', NOW(), NOW()),
(225, 'actualizar-estado-readingpromotionindicator', 'Actualizar Estado Readingpromotionindicator', 'ReadingPromotionIndicator', 'web', NOW(), NOW());

-- Modelo: IntegralClassroomSupport
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(226, 'ver-integralclassroomsupport', 'Ver Integralclassroomsupport', 'IntegralClassroomSupport', 'web', NOW(), NOW()),
(227, 'crear-integralclassroomsupport', 'Crear Integralclassroomsupport', 'IntegralClassroomSupport', 'web', NOW(), NOW()),
(228, 'editar-integralclassroomsupport', 'Editar Integralclassroomsupport', 'IntegralClassroomSupport', 'web', NOW(), NOW()),
(229, 'borrar-integralclassroomsupport', 'Borrar Integralclassroomsupport', 'IntegralClassroomSupport', 'web', NOW(), NOW()),
(230, 'actualizar-estado-integralclassroomsupport', 'Actualizar Estado Integralclassroomsupport', 'IntegralClassroomSupport', 'web', NOW(), NOW());

-- Modelo: IntegralClassroomSupportIndicator
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(231, 'ver-integralclassroomsupportindicator', 'Ver Integralclassroomsupportindicator', 'IntegralClassroomSupportIndicator', 'web', NOW(), NOW()),
(232, 'crear-integralclassroomsupportindicator', 'Crear Integralclassroomsupportindicator', 'IntegralClassroomSupportIndicator', 'web', NOW(), NOW()),
(233, 'editar-integralclassroomsupportindicator', 'Editar Integralclassroomsupportindicator', 'IntegralClassroomSupportIndicator', 'web', NOW(), NOW()),
(234, 'borrar-integralclassroomsupportindicator', 'Borrar Integralclassroomsupportindicator', 'IntegralClassroomSupportIndicator', 'web', NOW(), NOW()),
(235, 'actualizar-estado-integralclassroomsupportindicator', 'Actualizar Estado Integralclassroomsupportindicator', 'IntegralClassroomSupportIndicator', 'web', NOW(), NOW());

-- Modelo: NotificationChannel
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(236, 'ver-notificationchannel', 'Ver Notificationchannel', 'NotificationChannel', 'web', NOW(), NOW()),
(237, 'crear-notificationchannel', 'Crear Notificationchannel', 'NotificationChannel', 'web', NOW(), NOW()),
(238, 'editar-notificationchannel', 'Editar Notificationchannel', 'NotificationChannel', 'web', NOW(), NOW()),
(239, 'borrar-notificationchannel', 'Borrar Notificationchannel', 'NotificationChannel', 'web', NOW(), NOW()),
(240, 'actualizar-estado-notificationchannel', 'Actualizar Estado Notificationchannel', 'NotificationChannel', 'web', NOW(), NOW());

-- Modelo: IncidentReport
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(241, 'ver-incidentreport', 'Ver Incidentreport', 'IncidentReport', 'web', NOW(), NOW()),
(242, 'crear-incidentreport', 'Crear Incidentreport', 'IncidentReport', 'web', NOW(), NOW()),
(243, 'editar-incidentreport', 'Editar Incidentreport', 'IncidentReport', 'web', NOW(), NOW()),
(244, 'borrar-incidentreport', 'Borrar Incidentreport', 'IncidentReport', 'web', NOW(), NOW()),
(245, 'actualizar-estado-incidentreport', 'Actualizar Estado Incidentreport', 'IncidentReport', 'web', NOW(), NOW());

-- Modelo: IncidentIntervention
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(246, 'ver-incidentintervention', 'Ver Incidentintervention', 'IncidentIntervention', 'web', NOW(), NOW()),
(247, 'crear-incidentintervention', 'Crear Incidentintervention', 'IncidentIntervention', 'web', NOW(), NOW()),
(248, 'editar-incidentintervention', 'Editar Incidentintervention', 'IncidentIntervention', 'web', NOW(), NOW()),
(249, 'borrar-incidentintervention', 'Borrar Incidentintervention', 'IncidentIntervention', 'web', NOW(), NOW()),
(250, 'actualizar-estado-incidentintervention', 'Actualizar Estado Incidentintervention', 'IncidentIntervention', 'web', NOW(), NOW());

-- Modelo: IncidentCommitmentLetter
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(251, 'ver-incidentcommitmentletter', 'Ver Incidentcommitmentletter', 'IncidentCommitmentLetter', 'web', NOW(), NOW()),
(252, 'crear-incidentcommitmentletter', 'Crear Incidentcommitmentletter', 'IncidentCommitmentLetter', 'web', NOW(), NOW()),
(253, 'editar-incidentcommitmentletter', 'Editar Incidentcommitmentletter', 'IncidentCommitmentLetter', 'web', NOW(), NOW()),
(254, 'borrar-incidentcommitmentletter', 'Borrar Incidentcommitmentletter', 'IncidentCommitmentLetter', 'web', NOW(), NOW()),
(255, 'actualizar-estado-incidentcommitmentletter', 'Actualizar Estado Incidentcommitmentletter', 'IncidentCommitmentLetter', 'web', NOW(), NOW());

-- Modelo: SyncTombstone
INSERT INTO permissions (id, name, label, module, guard_name, created_at, updated_at) VALUES
(256, 'ver-synctombstone', 'Ver Synctombstone', 'SyncTombstone', 'web', NOW(), NOW()),
(257, 'crear-synctombstone', 'Crear Synctombstone', 'SyncTombstone', 'web', NOW(), NOW()),
(258, 'editar-synctombstone', 'Editar Synctombstone', 'SyncTombstone', 'web', NOW(), NOW()),
(259, 'borrar-synctombstone', 'Borrar Synctombstone', 'SyncTombstone', 'web', NOW(), NOW()),
(260, 'actualizar-estado-synctombstone', 'Actualizar Estado Synctombstone', 'SyncTombstone', 'web', NOW(), NOW());

-- Resetear secuencia de permissions
SELECT setval('permissions_id_seq', 260);

-- ============================================================
-- 2. ROLES (11 roles)
-- ============================================================
INSERT INTO roles (id, name, description, guard_name, created_at, updated_at) VALUES
(1, 'SUPER-ADMIN', 'Super Administrador pueden realizar cualquier accion', 'web', NOW(), NOW()),
(2, 'ADMIN', 'Administrador estan habilitados para leer,crear,actualizar,compartir,firmar documentos', 'web', NOW(), NOW()),
(3, 'RECTOR', 'Rector estan habilitados para leer ,firmar documentos)', 'web', NOW(), NOW()),
(4, 'VICERRECTOR', 'Vicerrector estan habilitados para leer (todos los documentos)- crear(año lectivo-periodo academico-areas- tipo documento-asignar directores) y actualizar(estado usuario,año lectivo-periodo academico-areas- tipo documento-asignar directores)', 'web', NOW(), NOW()),
(5, 'INSPECTOR', 'Rector estan habilitados para leer ,firmar documentos,revisar asistencias)', 'web', NOW(), NOW()),
(6, 'DIR-AREA', 'Director de Area  estan habilitados para leer(documentos area),crear(equipos), actualizar (estado usuario,equipos)', 'web', NOW(), NOW()),
(7, 'DECE', 'Ps Dece estan habilitados para leer documentos NEE ,firmar documentos de NEE', 'web', NOW(), NOW()),
(8, 'TUTOR', 'Generar Documentacion de curso asignado, firmar documentos de curso asignado, llevar asistencia de curso asignado,subir listado estudiantes del curso asignado', 'web', NOW(), NOW()),
(9, 'DOCENTE', 'Docente estan habilitados para leer- crear- actualizar-compartir (documentos,notas,horario de clases) firmar documentos de curso asignado, llevar asistencia de curso asignado, subir listado estudiantes del curso asignado', 'web', NOW(), NOW()),
(10, 'ESTUDIANTE', 'Estudiante esta habilitado para ver horario de clases, ver documentos de curso asignado, ver asistencia de curso asignado, ver notas', 'web', NOW(), NOW()),
(11, 'REPRESENTANTE', 'Representante esta habilitado para ver horario de clases, ver notas , ver asitencia, ver noitificaciones de estudiante seleccionado, justificar inasistencia de 1 dia', 'web', NOW(), NOW());

-- Resetear secuencia de roles
SELECT setval('roles_id_seq', 11);

-- ============================================================
-- 3. ASIGNACION DE PERMISOS A ROLES
-- ============================================================

-- SUPER-ADMIN: Todos los permisos (1-260)
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- ADMIN: Todos los permisos (1-260)
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 2, id FROM permissions;

-- RECTOR: ver-* (todos) + crear/editar de gestion academica + actualizar-estado-user
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name LIKE 'ver-%'
UNION ALL
SELECT 3, id FROM permissions WHERE name IN (
    'crear-scolaryear', 'editar-scolaryear',
    'crear-academicperiod', 'editar-academicperiod',
    'crear-grade', 'editar-grade',
    'crear-nivel', 'editar-nivel',
    'crear-shift', 'editar-shift',
    'crear-area', 'editar-area',
    'crear-subject', 'editar-subject',
    'crear-gradingscheme', 'editar-gradingscheme',
    'actualizar-estado-user'
);

-- VICERRECTOR: Misma asignacion que RECTOR
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name LIKE 'ver-%'
UNION ALL
SELECT 4, id FROM permissions WHERE name IN (
    'crear-scolaryear', 'editar-scolaryear',
    'crear-academicperiod', 'editar-academicperiod',
    'crear-grade', 'editar-grade',
    'crear-nivel', 'editar-nivel',
    'crear-shift', 'editar-shift',
    'crear-area', 'editar-area',
    'crear-subject', 'editar-subject',
    'crear-gradingscheme', 'editar-gradingscheme',
    'actualizar-estado-user'
);

-- INSPECTOR: ver-* (todos) + attendance/attendancesummary/classobservation CRUD
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE name LIKE 'ver-%'
UNION ALL
SELECT 5, id FROM permissions WHERE name IN (
    'crear-attendance', 'editar-attendance',
    'crear-attendancesummary', 'editar-attendancesummary',
    'crear-classobservation', 'editar-classobservation'
);

-- DIR-AREA: ver-* (todos) + crear/editar/actualizar-estado-user
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE name LIKE 'ver-%'
UNION ALL
SELECT 6, id FROM permissions WHERE name IN (
    'crear-user', 'editar-user', 'actualizar-estado-user'
);

-- DECE: solo ver-* (todos los permisos de lectura)
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE name LIKE 'ver-%';

-- DOCENTE: CRUD academico + asistencias + estudiantes
-- Patrones: ver-%, crear-%, editar-%, y permisos especificos de attendance, attendancesummary,
-- classobservation, activitygrade, activityrecovery, studentexam, studentproject,
-- classschedule, homeworkpending, document
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 9, id FROM permissions WHERE name LIKE 'ver-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE 'crear-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE 'editar-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-attendance'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-attendance-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-attendancesummary'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-attendancesummary-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-classobservation'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-classobservation-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-activitygrade'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-activitygrade-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-activityrecovery'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-activityrecovery-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-studentexam'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-studentexam-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-studentproject'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-studentproject-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-classschedule'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-classschedule-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-homeworkpending'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-homeworkpending-%'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-document'
UNION ALL
SELECT 9, id FROM permissions WHERE name LIKE '%-document-%';

-- TUTOR: Misma asignacion que DOCENTE
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE name LIKE 'ver-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE 'crear-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE 'editar-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-attendance'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-attendance-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-attendancesummary'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-attendancesummary-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-classobservation'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-classobservation-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-activitygrade'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-activitygrade-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-activityrecovery'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-activityrecovery-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-studentexam'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-studentexam-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-studentproject'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-studentproject-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-classschedule'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-classschedule-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-homeworkpending'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-homeworkpending-%'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-document'
UNION ALL
SELECT 8, id FROM permissions WHERE name LIKE '%-document-%';

-- ESTUDIANTE: solo ver-* (todos los permisos de lectura)
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 10, id FROM permissions WHERE name LIKE 'ver-%';

-- REPRESENTANTE: solo ver-* (todos los permisos de lectura)
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT 11, id FROM permissions WHERE name LIKE 'ver-%';

-- ============================================================
-- 4. ESCUELA
-- ============================================================
INSERT INTO schools (id, name_school, distrit, location, address, phone, email, website, logo_path, report_logo_path, status, created_at, updated_at) VALUES
(1, 'Unidad Educativa Vicente Leon', 'DISTRITO 05D01 - CIRCUITO C6_11 - AMIE 05H00091', 'Latacunga -Cotopaxi- Ecuador', 'Av.Tahuantinsuyo y Cañaris/Sector la Cocha', '9999999999', 'info@uevicenteleon.com', 'https://uevicenteleon.edu.ec', 'app-resources/img/logos/ue-vicente-leon.jpg', 'app-resources/img/logos/ue-vicente-leon.jpg', 1, NOW(), NOW());

-- ============================================================
-- 5. ANIO ESCOLAR 2026-2027
-- ============================================================
INSERT INTO scolar_years (id, year_name, start_date, end_date, status, created_at, updated_at) VALUES
(1, '2026-2027', '2026-08-12', '2027-07-10', 1, NOW(), NOW());

-- ============================================================
-- 6. TRIMESTRES (Periodos Academicos)
-- ============================================================
INSERT INTO academic_periods (id, year_id, trimester_name, start_date, end_date, grading_open_date, grading_close_date, is_supletorio, status, created_at, updated_at) VALUES
(1, 1, 'Primer Trimestre',   '2026-09-01', '2026-12-01', '2026-09-01', '2026-12-07', false, 1, NOW(), NOW()),
(2, 1, 'Segundo Trimestre',  '2026-12-02', '2027-03-15', '2026-12-02', '2027-03-22', false, 1, NOW(), NOW()),
(3, 1, 'Tercer Trimestre',   '2027-03-16', '2027-06-16', '2027-03-16', '2027-06-23', false, 1, NOW(), NOW()),
(4, 1, 'Supletorio',         '2027-06-17', '2027-07-06', '2027-06-17', '2027-07-10', true,  1, NOW(), NOW());

-- ============================================================
-- 7. ESQUEMA DE CALIFICACIONES
-- ============================================================
INSERT INTO grading_schemes (id, year_id, formative_percentage, summative_percentage, exam_percentage, project_percentage, status, created_at, updated_at) VALUES
(1, 1, 70.00, 30.00, 20.00, 10.00, 1, NOW(), NOW());

-- ============================================================
-- 8. TURNOS (Shifts)
-- ============================================================
INSERT INTO shifts (id, shift_name, status, created_at, updated_at) VALUES
(1, 'MATUTINA',  1, NOW(), NOW()),
(2, 'VESPERTINA', 1, NOW(), NOW()),
(3, 'INTENSIVO',  0, NOW(), NOW());

-- ============================================================
-- 9. NIVELES (9 niveles por turno = 27 niveles total)
-- ============================================================

-- Turno MATUTINA (shift_id = 1)
INSERT INTO nivels (id, shift_id, nivel_name, status, created_at, updated_at) VALUES
(1,  1, 'Educación_Inicial',                                         1, NOW(), NOW()),
(2,  1, 'Educación_General_Básica_Preparatoria',                     1, NOW(), NOW()),
(3,  1, 'Educación_General_Básica_Elemental',                        1, NOW(), NOW()),
(4,  1, 'Educación_General_Básica_Media',                            1, NOW(), NOW()),
(5,  1, 'Educación_General_Básica_Superior',                         1, NOW(), NOW()),
(6,  1, 'Bachillerato_General_Unificado',                            1, NOW(), NOW()),
(7,  1, 'Bachillerato_Técnico_Inf-Desarrollo de Soft',              1, NOW(), NOW()),
(8,  1, 'Bachillerato_Técnico_Com-Gestion y Log',                   1, NOW(), NOW()),
(9,  1, 'Bachillerato_Técnico_Promotor_Rec_Dep-Actividad_Fis_Dep_Rec', 1, NOW(), NOW());

-- Turno VESPERTINA (shift_id = 2)
INSERT INTO nivels (id, shift_id, nivel_name, status, created_at, updated_at) VALUES
(10, 2, 'Educación_Inicial',                                         1, NOW(), NOW()),
(11, 2, 'Educación_General_Básica_Preparatoria',                     1, NOW(), NOW()),
(12, 2, 'Educación_General_Básica_Elemental',                        1, NOW(), NOW()),
(13, 2, 'Educación_General_Básica_Media',                            1, NOW(), NOW()),
(14, 2, 'Educación_General_Básica_Superior',                         1, NOW(), NOW()),
(15, 2, 'Bachillerato_General_Unificado',                            1, NOW(), NOW()),
(16, 2, 'Bachillerato_Técnico_Inf-Desarrollo de Soft',              1, NOW(), NOW()),
(17, 2, 'Bachillerato_Técnico_Com-Gestion y Log',                   1, NOW(), NOW()),
(18, 2, 'Bachillerato_Técnico_Promotor_Rec_Dep-Actividad_Fis_Dep_Rec', 1, NOW(), NOW());

-- Turno INTENSIVO (shift_id = 3)
INSERT INTO nivels (id, shift_id, nivel_name, status, created_at, updated_at) VALUES
(19, 3, 'Educación_Inicial',                                         1, NOW(), NOW()),
(20, 3, 'Educación_General_Básica_Preparatoria',                     1, NOW(), NOW()),
(21, 3, 'Educación_General_Básica_Elemental',                        1, NOW(), NOW()),
(22, 3, 'Educación_General_Básica_Media',                            1, NOW(), NOW()),
(23, 3, 'Educación_General_Básica_Superior',                         1, NOW(), NOW()),
(24, 3, 'Bachillerato_General_Unificado',                            1, NOW(), NOW()),
(25, 3, 'Bachillerato_Técnico_Inf-Desarrollo de Soft',              1, NOW(), NOW()),
(26, 3, 'Bachillerato_Técnico_Com-Gestion y Log',                   1, NOW(), NOW()),
(27, 3, 'Bachillerato_Técnico_Promotor_Rec_Dep-Actividad_Fis_Dep_Rec', 1, NOW(), NOW());

-- Resetear secuencia de nivels
SELECT setval('nivels_id_seq', 27);

-- ============================================================
-- 10. GRADOS (Grades) - Solo para nivel_id 5 (Educación_General_Básica_Superior, Matutina)
--     Se crean todos los grados de TODOS los niveles de TODOS los turnos
-- ============================================================

-- Nivel 1: Educación_Inicial (Matutina) - grados 1° y 2°, secciones A-F
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 1, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° Educación Inicial'), ('2° Educación Inicial')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 2: Educación_General_Básica_Preparatoria (Matutina) - grados 1° y 2°, secciones A-F
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 2, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° EGB Preparatoria'), ('2° EGB Preparatoria')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 3: Educación_General_Básica_Elemental (Matutina) - grados 3° y 4°, secciones A-F
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 3, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('3° EGB Basica Elemental'), ('4° EGB Basica Elemental')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 4: Educación_General_Básica_Media (Matutina) - grados 5°, 6°, 7°, secciones A-F
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 4, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('5° EGB Basica Media'), ('6° EGB Basica Media'), ('7° EGB Basica Media')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 5: Educación_General_Básica_Superior (Matutina) - grados 8°, 9°, 10°, secciones A-F
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 5, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('8° EGB Basica Superior'), ('9° EGB Basica Superior'), ('10° EGB Basica Superior')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 6: Bachillerato_General_Unificado (Matutina) - grados 1°-3°, secciones A-C
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 6, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BGU General Unificado'), ('2° BGU General Unificado'), ('3° BGU General Unificado')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C')) AS s(seccion);

-- Nivel 7: Bachillerato_Técnico_Inf-Desarrollo de Soft (Matutina) - grados 1°-3°, secciones A-B
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 7, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Desarrollo Software'), ('2° BT Técnico Desarrollo Software'), ('3° BT Técnico Desarrollo Software')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
-- Grado 3° adicional para Técnico Inf
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 7, '3° BT Técnico Inf', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Nivel 8: Bachillerato_Técnico_Com-Gestion y Log (Matutina) - grados 1°-3°, secciones A-B
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 8, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Gestion y Logística'), ('2° BT Técnico Gestion y Logística'), ('3° BT Técnico Gestion y Logística')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
-- Grado 3° adicional para Técnico Com
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 8, '3° BT Técnico Com', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Nivel 9: Bachillerato_Técnico_Promotor_Rec_Dep (Matutina) - grados 1°-3°, secciones A-B
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 9, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Actividad Fis, Dep y Rec'), ('2° BT Técnico Actividad Fis, Dep y Rec'), ('3° BT Técnico Actividad Fis, Dep y Rec')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
-- Grado 3° adicional para Técnico Promotor
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 9, '3° BT Técnico Promotor en Rec y Dep', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Niveles 10-18: VESPERTINA (misma estructura, shift_id = 2)
-- Nivel 10: Educación_Inicial (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 10, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° Educación Inicial'), ('2° Educación Inicial')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 11: Educación_General_Básica_Preparatoria (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 11, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° EGB Preparatoria'), ('2° EGB Preparatoria')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 12: Educación_General_Básica_Elemental (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 12, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('3° EGB Basica Elemental'), ('4° EGB Basica Elemental')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 13: Educación_General_Básica_Media (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 13, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('5° EGB Basica Media'), ('6° EGB Basica Media'), ('7° EGB Basica Media')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 14: Educación_General_Básica_Superior (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 14, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('8° EGB Basica Superior'), ('9° EGB Basica Superior'), ('10° EGB Basica Superior')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 15: Bachillerato_General_Unificado (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 15, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BGU General Unificado'), ('2° BGU General Unificado'), ('3° BGU General Unificado')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C')) AS s(seccion);

-- Nivel 16: Bachillerato_Técnico_Inf-Desarrollo de Soft (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 16, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Desarrollo Software'), ('2° BT Técnico Desarrollo Software'), ('3° BT Técnico Desarrollo Software')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 16, '3° BT Técnico Inf', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Nivel 17: Bachillerato_Técnico_Com-Gestion y Log (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 17, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Gestion y Logística'), ('2° BT Técnico Gestion y Logística'), ('3° BT Técnico Gestion y Logística')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 17, '3° BT Técnico Com', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Nivel 18: Bachillerato_Técnico_Promotor_Rec_Dep (Vespertina)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 18, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Actividad Fis, Dep y Rec'), ('2° BT Técnico Actividad Fis, Dep y Rec'), ('3° BT Técnico Actividad Fis, Dep y Rec')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 18, '3° BT Técnico Promotor en Rec y Dep', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Niveles 19-27: INTENSIVO (misma estructura, shift_id = 3)
-- Nivel 19: Educación_Inicial (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 19, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° Educación Inicial'), ('2° Educación Inicial')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 20: Educación_General_Básica_Preparatoria (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 20, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° EGB Preparatoria'), ('2° EGB Preparatoria')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 21: Educación_General_Básica_Elemental (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 21, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('3° EGB Basica Elemental'), ('4° EGB Basica Elemental')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 22: Educación_General_Básica_Media (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 22, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('5° EGB Basica Media'), ('6° EGB Basica Media'), ('7° EGB Basica Media')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 23: Educación_General_Básica_Superior (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 23, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('8° EGB Basica Superior'), ('9° EGB Basica Superior'), ('10° EGB Basica Superior')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F')) AS s(seccion);

-- Nivel 24: Bachillerato_General_Unificado (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 24, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BGU General Unificado'), ('2° BGU General Unificado'), ('3° BGU General Unificado')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B'), ('C')) AS s(seccion);

-- Nivel 25: Bachillerato_Técnico_Inf-Desarrollo de Soft (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 25, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Desarrollo Software'), ('2° BT Técnico Desarrollo Software'), ('3° BT Técnico Desarrollo Software')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 25, '3° BT Técnico Inf', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Nivel 26: Bachillerato_Técnico_Com-Gestion y Log (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 26, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Gestion y Logística'), ('2° BT Técnico Gestion y Logística'), ('3° BT Técnico Gestion y Logística')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 26, '3° BT Técnico Com', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- Nivel 27: Bachillerato_Técnico_Promotor_Rec_Dep (Intensivo)
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 27, g.grado, s.seccion, 1, NOW(), NOW()
FROM (VALUES ('1° BT Técnico Actividad Fis, Dep y Rec'), ('2° BT Técnico Actividad Fis, Dep y Rec'), ('3° BT Técnico Actividad Fis, Dep y Rec')) AS g(grado)
CROSS JOIN (VALUES ('A'), ('B')) AS s(seccion);
INSERT INTO grades (nivel_id, grade_name, section, status, created_at, updated_at)
SELECT 27, '3° BT Técnico Promotor en Rec y Dep', s.seccion, 1, NOW(), NOW()
FROM (VALUES ('A'), ('B')) AS s(seccion);

-- ============================================================
-- 11. AREAS
-- ============================================================
INSERT INTO areas (id, area_name, created_at, updated_at) VALUES
(1,  'Inicial',                                                                       NOW(), NOW()),
(2,  'Basica Preparatoria',                                                           NOW(), NOW()),
(3,  'Basica Media',                                                                  NOW(), NOW()),
(4,  'Ciencias Naturales, Biologia y Fisica',                                         NOW(), NOW()),
(5,  'Educación Cultural y Artística',                                                NOW(), NOW()),
(6,  'Estudios Sociales',                                                             NOW(), NOW()),
(7,  'Matematica',                                                                    NOW(), NOW()),
(8,  'Lengua Extranjera',                                                             NOW(), NOW()),
(9,  'Lengua y Literatura',                                                           NOW(), NOW()),
(10, 'BT Comercio y Ventas -Emprendimiento- Gestion Administrativa y Logistica',      NOW(), NOW()),
(11, 'BT Deportes y Recreacion-Educación Física',                                     NOW(), NOW()),
(12, 'BT Informatica-Desarrollo de Software',                                         NOW(), NOW()),
(13, 'Optativas',                                                                     NOW(), NOW()),
(14, 'Tutoria',                                                                       NOW(), NOW());

-- Resetear secuencia de areas
SELECT setval('areas_id_seq', 14);

-- ============================================================
-- 12. MATERIAS (Subjects)
-- ============================================================

-- Area 1: Inicial
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(1, 'Currículo Integrado por ámbitos de aprendizaje', NOW(), NOW());

-- Area 2: Basica Preparatoria
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(2, 'Currículo Integrado por ámbitos', NOW(), NOW());

-- Area 3: Basica Media
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(3, 'Matemáticas', NOW(), NOW()),
(3, 'Ciencias Naturales', NOW(), NOW()),
(3, 'Lengua y Literatura', NOW(), NOW()),
(3, 'Estudios Sociales', NOW(), NOW());

-- Area 4: Ciencias Naturales, Biologia y Fisica
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(4, 'Ciencias Naturales', NOW(), NOW()),
(4, 'Química', NOW(), NOW()),
(4, 'Quimica Superior', NOW(), NOW()),
(4, 'Biología', NOW(), NOW()),
(4, 'Biología Superior', NOW(), NOW()),
(4, 'Fisica', NOW(), NOW()),
(4, 'Fisica Superior', NOW(), NOW());

-- Area 5: Educación Cultural y Artística
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(5, 'Educación Cultural y Artística', NOW(), NOW()),
(5, 'Dibujo Técnico Aplicado a Comercialización y Ventas', NOW(), NOW());

-- Area 6: Estudios Sociales
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(6, 'Estudios Sociales', NOW(), NOW()),
(6, 'Filosofía', NOW(), NOW()),
(6, 'Historia', NOW(), NOW()),
(6, 'Educación para la Ciudadanía', NOW(), NOW()),
(6, 'Investigacion Ciencia y Tecnologia', NOW(), NOW());

-- Area 7: Matematica
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(7, 'Matemáticas', NOW(), NOW()),
(7, 'Matematica Superior', NOW(), NOW());

-- Area 8: Lengua Extranjera
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(8, 'Inglés', NOW(), NOW()),
(8, 'Inglés Técnico Aplicado a Comercialización y Ventas', NOW(), NOW()),
(8, 'Inglés Técnico Aplicado a los Negocios', NOW(), NOW());

-- Area 9: Lengua y Literatura
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(9, 'Lengua y Literatura', NOW(), NOW()),
(9, 'Animación a la lectura', NOW(), NOW());

-- Area 10: BT Comercio y Ventas
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(10, 'Herramientas Informaticas Empresariales', NOW(), NOW()),
(10, 'Gestión Contable y Administracion Financiera', NOW(), NOW()),
(10, 'Compras y Logistica', NOW(), NOW()),
(10, 'Gestión Comercial y Comunicacion', NOW(), NOW()),
(10, 'Gestión de Procesos Administrativos', NOW(), NOW()),
(10, 'Emprendimiento y Gestión', NOW(), NOW()),
(10, 'Animación en el Punto de Venta', NOW(), NOW()),
(10, 'Operaciones de Venta', NOW(), NOW()),
(10, 'Operaciones de Almacenaje', NOW(), NOW()),
(10, 'Informática Aplicada a Comercialización y Ventas', NOW(), NOW()),
(10, 'Formación y Orientación Laboral - FOL-COMER', NOW(), NOW());

-- Area 11: BT Deportes y Recreacion
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(11, 'Salud, hábitos y práctica recreativa', NOW(), NOW()),
(11, 'Desarrollo deportivo y cultural', NOW(), NOW()),
(11, 'Administración deportiva y cultural', NOW(), NOW()),
(11, 'Planificación de actividades deportivas y recreativas ', NOW(), NOW()),
(11, 'Sesiones deportivas y recreativas', NOW(), NOW()),
(11, 'Promoción de la salud y valores en la práctica deportiva ', NOW(), NOW()),
(11, 'Seguridad, higiene y primeros auxilios deportivos ', NOW(), NOW()),
(11, 'Educación Física', NOW(), NOW()),
(11, 'Actividades Recreativas', NOW(), NOW()),
(11, 'Planificación y Evaluación en Recreación y Deportes', NOW(), NOW()),
(11, 'Entrenamiento Deportivo', NOW(), NOW()),
(11, 'Organización de Eventos Recreativos y/o Deportivos', NOW(), NOW()),
(11, 'Bases Fisiológicas', NOW(), NOW()),
(11, 'Manejo de Grupos', NOW(), NOW()),
(11, 'Seguridad y Primeros Auxilios', NOW(), NOW()),
(11, 'Recursos Recreativos y Deportivos', NOW(), NOW()),
(11, 'Formación y Orientación Laboral - FOL-DEPORTES', NOW(), NOW());

-- Area 12: BT Informatica-Desarrollo de Software
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(12, 'Fundamentos de las Tecnologias de la Informacion y Com', NOW(), NOW()),
(12, 'Pensamiento Computacional y Resolucion de Problemas', NOW(), NOW()),
(12, 'Etica, Legislacion y Ciudadania digital', NOW(), NOW()),
(12, 'Programación Estructurada', NOW(), NOW()),
(12, 'Programación Orientada a Objetos', NOW(), NOW()),
(12, 'Base de Datos', NOW(), NOW()),
(12, 'Aplicaciones de Escritorio', NOW(), NOW()),
(12, 'Aplicaciones WEB y Moviles', NOW(), NOW()),
(12, 'Modulo Practico Experimentnal', NOW(), NOW()),
(12, 'Programación y Bases de Datos', NOW(), NOW()),
(12, 'Diseño y Desarrollo WEB', NOW(), NOW()),
(12, 'Soporte Técnico', NOW(), NOW()),
(12, 'Sistemas Operativos y Redes', NOW(), NOW()),
(12, 'Aplicaciones Ofimáticas Locales y en Línea', NOW(), NOW()),
(12, 'Formación y Orientación Laboral - FOL-INFOR', NOW(), NOW());

-- Area 13: Optativas
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(13, 'Asignaturas optativas', NOW(), NOW()),
(13, 'Orientación vocacional y profesional', NOW(), NOW());

-- Area 14: Tutoria
INSERT INTO subjects (area_id, subject_name, created_at, updated_at) VALUES
(14, 'Acompañamiento integral en el aula', NOW(), NOW()),
(14, 'Cívica', NOW(), NOW());

-- ============================================================

COMMIT;

-- ============================================================
-- RESUMEN DE DATOS INSERTADOS:
-- - 260 permisos (52 modelos x 5 operaciones)
-- - 11 roles con permisos asignados
-- - 1 escuela
-- - 1 anio escolar (2026-2027)
-- - 4 trimestres (3 normales + 1 supletorio)
-- - 1 esquema de calificaciones (70% formativo + 30% sumativo)
-- - 3 turnos
-- - 27 niveles (9 por turno)
-- - ~396 grados (varia por nivel y secciones)
-- - 14 areas
-- - ~80 materias
-- - 7 usuarios (1 docente, 1 tutor, 5 estudiantes)
-- - 1 registro docente
-- - 5 registros estudiantes
-- - 5 matriculaciones
-- - 5 horarios de clase
-- ============================================================
