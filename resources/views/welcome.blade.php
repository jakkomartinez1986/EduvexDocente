<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Unidad Educativa Vicente León - Educación de Excelencia</title>
  
  <!-- Local Assets (self-contained, no CDN) -->
  <link rel="stylesheet" href="{{ asset('app-resources/css/google-fonts/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('app-resources/css/font-awesome/all.min.css') }}">
  <link rel="stylesheet" href="{{ asset('app-resources/css/landing-tailwind.css') }}">
  <link rel="stylesheet" href="{{ asset('app-resources/css/landing.css') }}">
</head>
<body class="bg-gray-50 font-sans text-gray-800">

  <!-- Barra superior -->
  <div class="bg-vicente-dark text-gray-300 text-sm py-2 hidden md:block">
    <div class="container mx-auto px-4 flex justify-between items-center">
      <div class="flex items-center space-x-6">
        <a href="mailto:info@vicenteleon.edu.ec" class="hover:text-vicente-gold transition flex items-center">
          <i class="fas fa-envelope mr-2"></i> info@vicenteleon.edu.ec
        </a>
        <a href="tel:+59332810500" class="hover:text-vicente-gold transition flex items-center">
          <i class="fas fa-phone-alt mr-2"></i> (03) 2810-500
        </a>
      </div>
      <div class="flex items-center space-x-4">
        <a href="#" class="hover:text-vicente-gold transition"><i class="fab fa-facebook-f"></i></a>
        <a href="#" class="hover:text-vicente-gold transition"><i class="fab fa-instagram"></i></a>
        <a href="#" class="hover:text-vicente-gold transition"><i class="fab fa-tiktok"></i></a>
        <a href="#" class="hover:text-vicente-gold transition"><i class="fab fa-youtube"></i></a>
      </div>
    </div>
  </div>

  <!-- Navegación principal (Sticky con efecto Glass) -->
  <header id="navbar" class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 transition-all duration-300">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
      <div class="flex items-center">
        <img src="{{ asset('app-resources/img/logos/ue-vicente-leon.jpg') }}" alt="Escudo Vicente León" class="h-14 mr-4 rounded-full shadow-sm">
        <div>
          <h1 class="text-lg font-bold text-gray-800 leading-tight">UNIDAD EDUCATIVA<br><span class="text-vicente-red">VICENTE LEÓN</span></h1>
        </div>
      </div>
      
      <!-- Menú Escritorio -->
      <nav class="hidden lg:flex items-center space-x-8">
        <a href="#inicio" class="nav-link text-gray-800 font-medium text-sm tracking-wider hover:text-vicente-red transition">INICIO</a>
        <a href="#nosotros" class="nav-link text-gray-800 font-medium text-sm tracking-wider hover:text-vicente-red transition">NOSOTROS</a>
        <a href="#academico" class="nav-link text-gray-800 font-medium text-sm tracking-wider hover:text-vicente-red transition">ACADÉMICO</a>
        <a href="#admisiones" class="nav-link text-gray-800 font-medium text-sm tracking-wider hover:text-vicente-red transition">ADMISIONES</a>
        <a href="#contacto" class="nav-link text-gray-800 font-medium text-sm tracking-wider hover:text-vicente-red transition">CONTACTO</a>
        
        @if(Route::has('login'))
          @auth
            <a href="{{ url('/dashboard') }}" class="bg-vicente-gold text-white font-bold px-5 py-2 rounded-lg shadow hover:bg-yellow-600 transition text-sm">
              DASHBOARD
            </a>
          @else
            <a href="{{ route('login') }}" class="nav-link text-gray-800 font-medium text-sm tracking-wider hover:text-vicente-red transition">SESIÓN</a>
            @if(Route::has('register'))
              <a href="{{ route('register') }}" class="bg-vicente-red text-white font-bold px-5 py-2 rounded-lg shadow hover:bg-red-800 transition text-sm">
                REGISTRO
              </a>
            @endif
          @endauth
        @endif
      </nav>
      
      <!-- Botón Menú Móvil -->
      <button id="mobile-menu-btn" class="lg:hidden text-gray-800 focus:outline-none">
        <i class="fas fa-bars text-2xl"></i>
      </button>
    </div>

    <!-- Menú Móvil (Oculto por defecto) -->
    <div id="mobile-menu" class="hidden lg:hidden bg-white border-t">
      <div class="flex flex-col px-4 pt-2 pb-4 space-y-3">
        <a href="#inicio" class="block px-4 py-2 text-gray-800 hover:bg-gray-100 rounded">INICIO</a>
        <a href="#nosotros" class="block px-4 py-2 text-gray-800 hover:bg-gray-100 rounded">NOSOTROS</a>
        <a href="#academico" class="block px-4 py-2 text-gray-800 hover:bg-gray-100 rounded">ACADÉMICO</a>
        <a href="#admisiones" class="block px-4 py-2 text-gray-800 hover:bg-gray-100 rounded">ADMISIONES</a>
        <a href="#contacto" class="block px-4 py-2 text-gray-800 hover:bg-gray-100 rounded">CONTACTO</a>
        @if(Route::has('login'))
          @auth
            <a href="{{ url('/dashboard') }}" class="block text-center bg-vicente-gold text-white font-bold px-4 py-2 rounded shadow">DASHBOARD</a>
          @else
            <a href="{{ route('login') }}" class="block text-center border border-vicente-red text-vicente-red font-bold px-4 py-2 rounded">INICIAR SESIÓN</a>
            @if(Route::has('register'))
              <a href="{{ route('register') }}" class="block text-center bg-vicente-red text-white font-bold px-4 py-2 rounded shadow">REGISTRO DOCENTES</a>
            @endif
          @endauth
        @endif
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section id="inicio" class="hero-bg text-white py-36 md:py-48 relative overflow-hidden">
    <div class="container mx-auto px-4 text-center relative z-10">
      <h2 class="text-4xl md:text-6xl lg:text-7xl font-bold mb-6 font-serif drop-shadow-lg tracking-tight">EDUCACIÓN CON <span class="text-vicente-gold">EXCELENCIA</span></h2>
      <p class="text-lg md:text-2xl mb-10 max-w-3xl mx-auto font-light leading-relaxed">Formamos líderes con valores, pensamiento crítico y compromiso social</p>
      <div class="flex flex-col sm:flex-row justify-center gap-4">
        <a href="#admisiones" class="bg-vicente-gold text-gray-900 font-bold px-10 py-4 rounded-lg shadow-xl hover:bg-yellow-500 hover:-translate-y-1 transition-all duration-300 text-lg">
          ADMISIONES {{ date('Y') }}
        </a>
        <a href="#contacto" class="bg-transparent border-2 border-white text-white font-bold px-10 py-4 rounded-lg hover:bg-white/10 hover:-translate-y-1 transition-all duration-300 shadow-xl text-lg">
          CONTÁCTANOS
        </a>
      </div>
    </div>
    <!-- Forma decorativa inferior -->
    <div class="absolute bottom-0 left-0 w-full overflow-hidden leading-none">
      <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
        <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" class="fill-gray-50"></path>
      </svg>
    </div>
  </section>

  <!-- Sección Destacada -->
  <section class="bg-gray-50 py-16">
    <div class="container mx-auto px-4">
      <div class="grid md:grid-cols-3 gap-8 -mt-24 md:-mt-32 relative z-20">
        <div class="scroll-animate bg-white p-8 rounded-2xl shadow-xl text-center hover:shadow-2xl transition-shadow duration-300 border-t-4 border-vicente-red">
          <div class="bg-red-50 w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-5">
            <i class="fas fa-graduation-cap text-vicente-red text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold mb-3 text-gray-800">EXCELENCIA ACADÉMICA</h3>
          <p class="text-gray-600">Programas educativos de alto nivel académico</p>
        </div>
        <div class="scroll-animate bg-white p-8 rounded-2xl shadow-xl text-center hover:shadow-2xl transition-shadow duration-300 border-t-4 border-vicente-gold" style="transition-delay: 0.2s">
          <div class="bg-yellow-50 w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-5">
            <i class="fas fa-users text-vicente-gold text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold mb-3 text-gray-800">FORMACIÓN INTEGRAL</h3>
          <p class="text-gray-600">Desarrollo de habilidades sociales y emocionales</p>
        </div>
        <div class="scroll-animate bg-white p-8 rounded-2xl shadow-xl text-center hover:shadow-2xl transition-shadow duration-300 border-t-4 border-vicente-red" style="transition-delay: 0.4s">
          <div class="bg-red-50 w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-5">
            <i class="fas fa-shield-alt text-vicente-red text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold mb-3 text-gray-800">VALORES</h3>
          <p class="text-gray-600">Educación basada en principios éticos y morales</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Sobre Nosotros -->
  <section id="nosotros" class="py-24 bg-white">
    <div class="container mx-auto px-4">
      <div class="grid lg:grid-cols-2 gap-16 items-center">
        <div class="scroll-animate">
          <span class="text-vicente-gold font-bold uppercase tracking-widest">Nuestra Historia</span>
          <h2 class="text-4xl font-bold text-gray-800 mt-2 mb-6 font-serif leading-tight">UN LEGADO DE MÁS DE 100 AÑOS</h2>
          <p class="text-gray-600 mb-4 leading-relaxed text-lg">Fundada en 1840, la Unidad Educativa Vicente León ha sido pionera en la educación de calidad en Latacunga, formando generaciones de profesionales y ciudadanos comprometidos con el desarrollo del país.</p>
          <p class="text-gray-600 mb-8 leading-relaxed text-lg italic">"Inmortal Juventud Adelante" refleja el espíritu de superación y progreso que inculcamos en nuestros estudiantes.</p>
          
          <div class="grid grid-cols-2 gap-6">
            <div class="bg-gray-50 p-6 rounded-xl border-l-4 border-vicente-red">
              <div class="text-vicente-red text-4xl font-bold mb-2">15K+</div>
              <p class="text-gray-600 font-medium">Egresados Exitosos</p>
            </div>
            <div class="bg-gray-50 p-6 rounded-xl border-l-4 border-vicente-gold">
              <div class="text-vicente-gold text-4xl font-bold mb-2">95%</div>
              <p class="text-gray-600 font-medium">Ingreso Universitario</p>
            </div>
          </div>
        </div>
        
        <div class="scroll-animate relative">
          <img src="{{ asset('app-resources/img/banners/vicente leon portada.png') }}" alt="Historia Vicente León" class="rounded-2xl shadow-2xl w-full object-cover h-[500px]">
          <div class="absolute -bottom-6 -left-6 bg-vicente-red text-white p-6 rounded-xl shadow-lg font-bold text-xl font-serif">
            Desde 1840
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Oferta Académica -->
  <section id="academico" class="py-24 bg-gray-50">
    <div class="container mx-auto px-4">
      <div class="text-center mb-16 scroll-animate">
        <span class="text-vicente-gold font-bold uppercase tracking-widest">Educación de Calidad</span>
        <h2 class="text-4xl font-bold text-gray-800 mt-2 font-serif">OFERTA ACADÉMICA</h2>
      </div>
      
      <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
         <!-- Educación Inicial -->
         <div class="scroll-animate bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300 group">
            <div class="h-48 overflow-hidden relative">
              <img src="{{ asset('app-resources/img/banners/banner-educacion-inicial.jpeg') }}" alt="Educación Inicial" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
              <div class="absolute top-4 right-4 bg-vicente-gold text-white text-xs font-bold px-3 py-1 rounded-full">1° - 2° EI</div>
            </div>
            <div class="p-6">
              <h3 class="text-xl font-bold text-gray-800 mb-3">EDUCACIÓN INICIAL</h3>
              <p class="text-gray-600 mb-4 text-sm leading-relaxed">Formación integral con énfasis en valores y habilidades fundamentales.</p>
              <a href="#" class="text-vicente-red font-semibold hover:text-red-800 transition text-sm flex items-center">
                Más información <i class="fas fa-arrow-right ml-2 text-xs"></i>
              </a>
            </div>
          </div>

        <!-- Educación Básica -->
        <div class="scroll-animate bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300 group" style="transition-delay: 0.1s">
          <div class="h-48 overflow-hidden relative">
            <img src="{{ asset('app-resources/img/banners/Educacion_general_basica_banner.png') }}" alt="Educación Básica" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <div class="absolute top-4 right-4 bg-vicente-red text-white text-xs font-bold px-3 py-1 rounded-full">1° - 10° EGB</div>
          </div>
          <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-3">EDUCACIÓN GENERAL BÁSICA</h3>
            <p class="text-gray-600 mb-4 text-sm leading-relaxed">Desarrollo del pensamiento crítico, lógico-matemático y cultural.</p>
            <a href="#" class="text-vicente-red font-semibold hover:text-red-800 transition text-sm flex items-center">
              Más información <i class="fas fa-arrow-right ml-2 text-xs"></i>
            </a>
          </div>
        </div>
        
        <!-- Bachillerato General -->
        <div class="scroll-animate bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300 group" style="transition-delay: 0.2s">
          <div class="h-48 overflow-hidden relative">
            <img src="{{ asset('app-resources/img/banners/bachillerato general unificado_banner.png') }}" alt="Bachillerato General" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <div class="absolute top-4 right-4 bg-vicente-gold text-white text-xs font-bold px-3 py-1 rounded-full">1° - 3° BGU</div>
          </div>
          <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-3">BACHILLERATO GENERAL</h3>
            <p class="text-gray-600 mb-4 text-sm leading-relaxed">Preparación universitaria con sólida base científica-humanística.</p>
            <a href="#" class="text-vicente-red font-semibold hover:text-red-800 transition text-sm flex items-center">
              Más información <i class="fas fa-arrow-right ml-2 text-xs"></i>
            </a>
          </div>
        </div>
        
        <!-- Bachillerato Técnico -->
        <div class="scroll-animate bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300 group" style="transition-delay: 0.3s">
          <div class="h-48 overflow-hidden relative">
             <img src="{{ asset('app-resources/img/banners/banner-bachillerato-tecnico.webp') }}" alt="Bachillerato Técnico" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
             <div class="absolute top-4 right-4 bg-vicente-red text-white text-xs font-bold px-3 py-1 rounded-full">1° - 3° BT</div>
          </div>
          <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-3">BACHILLERATO TÉCNICO</h3>
            <p class="text-gray-600 mb-4 text-sm leading-relaxed">Especialización técnica con salida laboral o continuación universitaria.</p>
            <a href="#" class="text-vicente-red font-semibold hover:text-red-800 transition text-sm flex items-center">
              Más información <i class="fas fa-arrow-right ml-2 text-xs"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Admisiones -->
  <section id="admisiones" class="py-24 bg-white relative overflow-hidden">
    <div class="absolute top-0 right-0 w-1/3 h-full bg-red-50 skew-x-[-20deg] transform origin-top-right hidden lg:block"></div>
    <div class="container mx-auto px-4 relative z-10">
      <div class="grid lg:grid-cols-2 gap-16 items-center">
        <div class="scroll-animate relative">
          <img src="{{ asset('app-resources/img/banners/banner-admisiones-vl-new.png') }}" alt="Proceso de admisión" class="rounded-2xl shadow-2xl w-full">
        </div>
        
        <div class="scroll-animate">
          <span class="text-vicente-gold font-bold uppercase tracking-widest">Próximo Año Lectivo</span>
          <h3 class="text-4xl font-bold text-gray-800 mt-2 mb-6 font-serif leading-tight">PROCESO DE ADMISIÓN {{ date('Y') + 1 }}</h3>
          <p class="text-gray-600 mb-6 leading-relaxed text-lg">Proceso de Admisión en la Página del Ministerio de Educación. Garantizamos un proceso transparente y accesible para todos los aspirantes.</p>
          
          <div class="space-y-4 mb-8">
            <div class="flex items-center text-gray-700">
              <i class="fas fa-check-circle text-vicente-gold mr-4 text-xl"></i>
              <span>Registro en plataforma oficial</span>
            </div>
            <div class="flex items-center text-gray-700">
              <i class="fas fa-check-circle text-vicente-gold mr-4 text-xl"></i>
              <span>Documentación requerida</span>
            </div>
            <div class="flex items-center text-gray-700">
              <i class="fas fa-check-circle text-vicente-gold mr-4 text-xl"></i>
              <span>Asignación de cupos</span>
            </div>
          </div>

          <a href="https://juntos.educacion.gob.ec/" target="_blank" class="bg-vicente-red text-white font-bold px-8 py-4 rounded-lg shadow-lg hover:bg-red-800 hover:-translate-y-1 transition-all duration-300 inline-flex items-center">
            IR A INSCRIPCIONES <i class="fas fa-external-link-alt ml-3"></i>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Contacto -->
  <section id="contacto" class="py-24 bg-gray-50">
    <div class="container mx-auto px-4">
      <div class="text-center mb-16 scroll-animate">
        <span class="text-vicente-gold font-bold uppercase tracking-widest">Estamos para ayudarte</span>
        <h2 class="text-4xl font-bold text-gray-800 mt-2 font-serif">CONTÁCTANOS</h2>
      </div>
      
      <div class="grid lg:grid-cols-2 gap-12">
        <div class="scroll-animate">
          <div class="space-y-6">
            <div class="flex items-start bg-white p-5 rounded-xl shadow-sm">
              <div class="bg-vicente-red text-white p-3 rounded-lg mr-5">
                <i class="fas fa-map-marker-alt text-xl"></i>
              </div>
              <div>
                <h4 class="font-bold text-gray-800 mb-1">Dirección</h4>
                <p class="text-gray-600">Av. Tahuantinsuyo y Cañaris, sector la Cocha, Latacunga, Ecuador</p>
              </div>
            </div>
            
            <div class="flex items-start bg-white p-5 rounded-xl shadow-sm">
              <div class="bg-vicente-red text-white p-3 rounded-lg mr-5">
                <i class="fas fa-phone-alt text-xl"></i>
              </div>
              <div>
                <h4 class="font-bold text-gray-800 mb-1">Teléfonos</h4>
                <p class="text-gray-600">(03) 2810-500 / (03) 2811-600</p>
              </div>
            </div>
            
            <div class="flex items-start bg-white p-5 rounded-xl shadow-sm">
              <div class="bg-vicente-red text-white p-3 rounded-lg mr-5">
                <i class="fas fa-envelope text-xl"></i>
              </div>
              <div>
                <h4 class="font-bold text-gray-800 mb-1">Correo electrónico</h4>
                <p class="text-gray-600">info@vicenteleon.edu.ec / admisiones@vicenteleon.edu.ec</p>
              </div>
            </div>
            
            <div class="flex items-start bg-white p-5 rounded-xl shadow-sm">
              <div class="bg-vicente-red text-white p-3 rounded-lg mr-5">
                <i class="fas fa-clock text-xl"></i>
              </div>
              <div>
                <h4 class="font-bold text-gray-800 mb-1">Horario de atención</h4>
                <p class="text-gray-600">Lunes a Viernes: 08:30 - 12:00 y 14:00 - 16:30</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="scroll-animate bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
          <h3 class="text-2xl font-bold text-gray-800 mb-6">ENVÍANOS UN MENSAJE</h3>
          
          <!-- Formulario seguro para Laravel -->
          {{-- <form action="{{ route('contact.store') }}" method="POST" class="space-y-5">
            @csrf
            <div class="grid sm:grid-cols-2 gap-5">
              <div>
                <label for="nombre" class="block text-gray-700 font-medium mb-2 text-sm">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-vicente-gold focus:border-transparent outline-none transition">
              </div>
              <div>
                <label for="email" class="block text-gray-700 font-medium mb-2 text-sm">Correo electrónico</label>
                <input type="email" id="email" name="email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-vicente-gold focus:border-transparent outline-none transition">
              </div>
            </div>
            
            <div>
              <label for="asunto" class="block text-gray-700 font-medium mb-2 text-sm">Asunto</label>
              <select id="asunto" name="asunto" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-vicente-gold focus:border-transparent outline-none transition">
                <option>Información general</option>
                <option>Detalles de cursos</option>
                <option>Solicitudes</option>
                <option>Otro</option>
              </select>
            </div>
            
            <div>
              <label for="mensaje" class="block text-gray-700 font-medium mb-2 text-sm">Mensaje</label>
              <textarea id="mensaje" name="mensaje" rows="4" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-vicente-gold focus:border-transparent outline-none transition resize-none"></textarea>
            </div>
            
            <button type="submit" class="bg-vicente-gold text-white font-bold w-full py-4 rounded-lg shadow-lg hover:bg-yellow-600 transition duration-300">
              ENVIAR MENSAJE
            </button>
          </form> --}}
        </div>
      </div>
    </div>
  </section>
 
  <!-- Mapa -->
  <div class="h-96 bg-gray-200 relative">
    <iframe 
      src="https://www.google.com/maps?q=-0.922039,-78.610117&hl=es&z=16&output=embed" 
      width="100%" 
      height="100%" 
      style="border:0;" 
      allowfullscreen="" 
      loading="lazy"
      class="absolute inset-0">
    </iframe>
  </div>

  <!-- Footer -->
  <footer class="bg-vicente-dark text-white pt-16 pb-8">
    <div class="container mx-auto px-4">
      <div class="grid md:grid-cols-4 gap-10 mb-12">
        <div>
          <h3 class="text-2xl font-bold mb-4 font-serif text-vicente-gold">VICENTE LEÓN</h3>
          <p class="text-gray-400 mb-4 leading-relaxed">Institución educativa con más de 100 años de tradición y excelencia en Latacunga.</p>
          <p class="italic text-gray-300 border-l-4 border-vicente-gold pl-3">"Inmortal Juventud Adelante"</p>
        </div>
        
        <div>
          <h4 class="font-bold text-white mb-5 uppercase tracking-wider text-sm">Enlaces Rápidos</h4>
          <ul class="space-y-3 text-gray-400">
            <li><a href="#inicio" class="hover:text-vicente-gold transition">Inicio</a></li>
            <li><a href="#nosotros" class="hover:text-vicente-gold transition">Nosotros</a></li>
            <li><a href="#academico" class="hover:text-vicente-gold transition">Oferta Académica</a></li>
            <li><a href="#admisiones" class="hover:text-vicente-gold transition">Admisiones</a></li>
          </ul>
        </div>
        
        <div>
          <h4 class="font-bold text-white mb-5 uppercase tracking-wider text-sm">Admisiones</h4>
          <ul class="space-y-3 text-gray-400">
            <li><a href="https://juntos.educacion.gob.ec/" target="_blank" class="hover:text-vicente-gold transition">Plataforma del Ministerio</a></li>
            <li><a href="#" class="hover:text-vicente-gold transition">Preguntas frecuentes</a></li>
          </ul>
        </div>
        
        <div>
          <h4 class="font-bold text-white mb-5 uppercase tracking-wider text-sm">Síguenos</h4>
          <div class="flex space-x-3 mb-6">
            <a href="#" class="w-10 h-10 rounded-lg bg-gray-700 flex items-center justify-center hover:bg-vicente-gold transition duration-300">
              <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="w-10 h-10 rounded-lg bg-gray-700 flex items-center justify-center hover:bg-vicente-gold transition duration-300">
              <i class="fab fa-instagram"></i>
            </a>
            <a href="#" class="w-10 h-10 rounded-lg bg-gray-700 flex items-center justify-center hover:bg-vicente-gold transition duration-300">
              <i class="fab fa-youtube"></i>
            </a>
          </div>
          <p class="text-gray-400 mb-2 text-sm">Suscríbete a nuestro boletín</p>
          <form class="flex">
            <input type="email" placeholder="Tu correo" class="px-4 py-2 w-full rounded-l-lg focus:outline-none text-gray-800 text-sm bg-gray-700 border border-gray-600 focus:bg-white">
            <button type="submit" class="bg-vicente-gold text-gray-900 px-4 py-2 rounded-r-lg font-bold hover:bg-yellow-400 transition">
              <i class="fas fa-paper-plane"></i>
            </button>
          </form>
        </div>
      </div>
      
      <div class="border-t border-gray-700 pt-8 flex flex-col md:flex-row justify-between items-center text-gray-500 text-sm">
        <p>&copy; {{ date('Y') }} RU-TAH. Todos los derechos reservados.</p>
        <p class="mt-2 md:mt-0">Latacunga - Ecuador</p>
      </div>
    </div>
  </footer>

  <!-- Scripts (self-contained, no CDN) -->
  <script src="{{ asset('app-resources/js/landing.js') }}"></script>
</body>
</html>
