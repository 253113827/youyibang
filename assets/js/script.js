// CoreUI风格的交互功能
document.addEventListener('DOMContentLoaded', function() {
  // 导航栏滚动效果
  const navbar = document.querySelector('.navbar');
  const headerHeight = navbar.offsetHeight;

  window.addEventListener('scroll', function() {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });

  // 平滑滚动
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        const offsetTop = target.offsetTop - headerHeight;
        window.scrollTo({
          top: offsetTop,
          behavior: 'smooth'
        });
      }
    });
  });

  // 特色卡片悬停效果
  const featureCards = document.querySelectorAll('.feature-card');
  featureCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-10px)';
    });
    
    card.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });

  // 合作伙伴卡片悬停效果
  const partnerBadges = document.querySelectorAll('.partner-badge');
  partnerBadges.forEach(badge => {
    badge.addEventListener('mouseenter', function() {
      this.style.transform = 'scale(1.03)';
    });
    
    badge.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
    });
  });

  // 滚动动画
  const fadeElements = document.querySelectorAll('.fade-in-up');
  
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('appear');
      }
    });
  }, observerOptions);

  fadeElements.forEach(element => {
    observer.observe(element);
  });

  // 表单提交处理（示例）
  const contactForms = document.querySelectorAll('form');
  contactForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      // 这里可以添加表单提交逻辑
      alert('感谢您的提交！我们会尽快与您联系。');
    });
  });

  // 搜索功能
  const searchButton = document.querySelector('.navbar-search');
  if (searchButton) {
    searchButton.addEventListener('click', function() {
      alert('搜索功能将在正式版本中提供');
    });
  }

  // 回到顶部按钮
  const topButton = document.createElement('button');
  topButton.innerHTML = '<i class="bi bi-arrow-up"></i>';
  topButton.className = 'btn btn-primary position-fixed rounded-circle';
  topButton.style.cssText = `
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    width: 50px;
    height: 50px;
    display: none;
    transition: all 0.3s;
  `;
  document.body.appendChild(topButton);

  window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
      topButton.style.display = 'block';
    } else {
      topButton.style.display = 'none';
    }
  });

  topButton.addEventListener('click', function() {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  // 页面加载动画
  document.body.style.opacity = '0';
  setTimeout(() => {
    document.body.style.transition = 'opacity 0.5s ease';
    document.body.style.opacity = '1';
  }, 100);

  // 数据统计动画
  function animateCounter(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
      if (!startTimestamp) startTimestamp = timestamp;
      const progress = Math.min((timestamp - startTimestamp) / duration, 1);
      const value = Math.floor(progress * (end - start) + start);
      element.textContent = value.toLocaleString();
      if (progress < 1) {
        window.requestAnimationFrame(step);
      }
    };
    window.requestAnimationFrame(step);
  }

  // 当统计区域进入视窗时启动计数器
  const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const counters = entry.target.querySelectorAll('.stats-number');
        counters.forEach(counter => {
          const targetValue = counter.getAttribute('data-target');
          if (targetValue) {
            animateCounter(counter, 0, parseInt(targetValue), 2000);
          }
        });
        statsObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  const statsSection = document.querySelector('.stats-section');
  if (statsSection) {
    statsObserver.observe(statsSection);
  }
});