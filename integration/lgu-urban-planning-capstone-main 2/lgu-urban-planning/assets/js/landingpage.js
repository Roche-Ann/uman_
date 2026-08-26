(function(){
  document.querySelectorAll('.modal').forEach(function(modalEl){
    modalEl.addEventListener('hide.bs.modal', function(){
      var focused = document.activeElement;
      if (focused && modalEl.contains(focused)){
        focused.blur();
      }
    });
  });

  // Navbar shrink on scroll + back-to-top visibility
  var navbar = document.getElementById('topNavbar');
  var backToTop = document.getElementById('backToTop');
  function onScroll(){
    var y = window.scrollY || document.documentElement.scrollTop;
    if (navbar) navbar.classList.toggle('is-scrolled', y > 24);
    if (backToTop) backToTop.classList.toggle('is-visible', y > 480);
  }
  document.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  if (backToTop){
    backToTop.addEventListener('click', function(){
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Mobile nav toggle
  var toggleBtn = document.getElementById('navToggleBtn');
  var mobileNav = document.getElementById('mobileNav');
  if (toggleBtn && mobileNav){
    toggleBtn.addEventListener('click', function(){
      var open = mobileNav.classList.toggle('is-open');
      toggleBtn.classList.toggle('is-open', open);
      toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    mobileNav.querySelectorAll('a').forEach(function(link){
      link.addEventListener('click', function(){
        mobileNav.classList.remove('is-open');
        toggleBtn.classList.remove('is-open');
        toggleBtn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Scroll-triggered reveal animations
  var revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry, i){
        if (entry.isIntersecting){
          var el = entry.target;
          setTimeout(function(){ el.classList.add('is-visible'); }, (i % 6) * 70);
          io.unobserve(el);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function(el){ io.observe(el); });
  } else {
    revealEls.forEach(function(el){ el.classList.add('is-visible'); });
  }

  // Animated counters for the trust strip
  var counters = document.querySelectorAll('.num[data-count]');
  function animateCounter(el){
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var suffix = el.getAttribute('data-suffix') || '';
    var duration = 1400;
    var start = null;
    function step(ts){
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var value = Math.round(target * eased);
      el.textContent = value.toLocaleString() + suffix;
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  if (counters.length){
    if ('IntersectionObserver' in window){
      var counterIo = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if (entry.isIntersecting){
            animateCounter(entry.target);
            counterIo.unobserve(entry.target);
          }
        });
      }, { threshold: 0.5 });
      counters.forEach(function(el){ counterIo.observe(el); });
    } else {
      counters.forEach(animateCounter);
    }
  }

  // Subtle cursor-tilt on the hero mock dashboard card
  var mockCard = document.querySelector('.mock-card');
  if (mockCard && window.matchMedia('(hover: hover)').matches){
    mockCard.addEventListener('mousemove', function(e){
      var rect = mockCard.getBoundingClientRect();
      var relX = (e.clientX - rect.left) / rect.width - 0.5;
      var relY = (e.clientY - rect.top) / rect.height - 0.5;
      mockCard.style.transform = 'rotate(1deg) translateY(-4px) rotateX(' + (relY * -6) + 'deg) rotateY(' + (relX * 8) + 'deg)';
    });
    mockCard.addEventListener('mouseleave', function(){
      mockCard.style.transform = '';
    });
  }
})();
