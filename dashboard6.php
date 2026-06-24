<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Shopping App UI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
    margin:0;
    background:#f1f3ff;
    font-family:"Segoe UI",sans-serif;
}
.app{
    max-width:430px;
    margin:auto;
    background:#fff;
    min-height:100vh;
    padding-top:105px; /* reduced after removing info strip */
    padding-bottom:80px;
}

/* FIXED HEADER */
.header-fixed{
    position:fixed;
    top:0;
    left:0;
    right:0;
    max-width:430px;
    margin:auto;
    z-index:1000;
}
/* Carousel Dots */
#heroCarousel .dots {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    justify-content: center;
    gap: 6px;
    z-index: 10;
}

#heroCarousel .dots span {
    width: 6px;
    height: 6px;
    background: #cdd3ff;
    border-radius: 50%;
    display: inline-block;
    cursor: pointer;
}

#heroCarousel .dots span.active {
    background: #fff;
}


/* TOP BAR */
.top-bar{
    background:linear-gradient(180deg,#5b73ff,#4c63e0);
    color:#fff;
    padding:12px 14px 14px;
    box-shadow:0 6px 18px rgba(0,0,0,.15);
}
.top-row{
    display:flex;
    align-items:center;
    gap:12px;
}
.top-row i{font-size:20px}
.location{
    font-weight:600;
    flex:1;
}

/* USER AVATAR */
.user-avatar{
    width:34px;
    height:34px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid rgba(255,255,255,.8);
}

/* ICON BADGE */
.icon-badge{
    position:relative;
}
.icon-badge span{
    position:absolute;
    top:-4px;
    right:-4px;
    background:#ff3b3b;
    color:#fff;
    font-size:9px;
    width:16px;
    height:16px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
}

/* SEARCH */
.search-box{
    background:#fff;
    border-radius:30px;
    padding:8px 14px;
    display:flex;
    align-items:center;
    gap:10px;
    margin-top:10px;
    box-shadow:0 4px 12px rgba(0,0,0,.12);
}
.search-box i{
    color:#6c75ff;
    font-size:18px;
}
.search-box input{
    border:none;
    flex:1;
    outline:none;
    font-size:14px;
}

/* HERO */
.hero{
    background:linear-gradient(135deg,#4f66ff,#6f83ff);
    border-radius:16px;
    padding:12px 14px;
    color:#fff;
    margin:14px 15px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 10px 24px rgba(79,102,255,.35);
}
.hero h5{
    margin:4px 0;
    font-weight:700;
    font-size:16px;
    line-height:1.2;
}
.hero small{
    font-size:12px;
    opacity:.9;
}
.hero img{
    width:90px;
}
.dots span{
    width:5px;
    height:5px;
    background:#cdd3ff;
    display:inline-block;
    border-radius:50%;
    margin-right:4px;
}
.dots span.active{background:#fff}

/* CATEGORY TABS */
.categories{
    display:flex;
    gap:10px;
    padding:8px 15px;
    overflow-x:auto;
    scrollbar-width:none;
}
.categories::-webkit-scrollbar{display:none}
.category{
    padding:6px 16px;
    border-radius:20px;
    background:#eef1ff;
    font-size:12.5px;
    white-space:nowrap;
    color:#333;
    font-weight:500;
}
.category.active{
    background:#4f66ff;
    color:#fff;
}

/* FEATURE CARD */
.card-box{
    background:#fff;
    border-radius:18px;
    padding:14px;
    margin:15px;
    display:flex;
    align-items:center;
    gap:12px;
    box-shadow:0 8px 24px rgba(0,0,0,.08);
}
.card-box img{width:90px;border-radius:10px}
/* SIDEBAR STYLES */
.sidebar {
    position: fixed;
    top: 0;
    left: -260px; /* hidden by default */
    width: 260px;
    height: 100%;
    background: #fff;
    box-shadow: 4px 0 12px rgba(0,0,0,.1);
    z-index: 1500;
    transition: left 0.3s ease;
    padding: 20px;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li {
    margin-bottom: 16px;
}

.sidebar-menu li a {
    text-decoration: none;
    color: #333;
    font-weight: 500;
    font-size: 14px;
}

/* OVERLAY */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.3);
    display: none;
    z-index: 1400;
}

/* OPEN STATE */
.sidebar.open {
    left: 0;
}

.sidebar-overlay.active {
    display: block;
}

/* OPEN BUTTON (optional floating) */
.sidebar-toggle {
    position: fixed;
    top: 16px;
    left: 16px;
    z-index: 1600;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* DEALS */
.deals{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    padding:0 15px;
}
.deal{
    border-radius:16px;
    padding:12px;
    font-size:13px;
    box-shadow:0 6px 18px rgba(0,0,0,.08);
}
.deal img{
    width:100%;
    margin-top:8px;
    border-radius:10px;
}
.deal.orange{background:#fff1e4}
.deal.blue{background:#edf2ff}

/* TRENDING */
.trending{
    padding:14px 15px;
}
.chip{
    background:#4f66ff;
    color:#fff;
    padding:6px 14px;
    border-radius:20px;
    font-size:12px;
    margin-right:6px;
    display:inline-block;
}

/* BOTTOM NAV */
.bottom-nav{
    position:fixed;
    bottom:0;
    left:0;
    right:0;
    background:#fff;
    border-top:1px solid #e5e5e5;
    max-width:430px;
    margin:auto;
    display:flex;
}
.bottom-nav a{
    flex:1;
    text-align:center;
    padding:8px 0;
    font-size:11px;
    color:#777;
    text-decoration:none;
}
.bottom-nav a i{font-size:20px}
.bottom-nav .active{color:#4f66ff}
</style>
</head>

<body>

<!-- FIXED HEADER -->
<div class="header-fixed">
    <div class="top-bar">
        <div class="top-row">
          <i class="bi bi-list" id="openSidebar" style="cursor:pointer;"></i>

            <div class="location">Pane <i class="bi bi-chevron-down"></i></div>
            <div class="icon-badge">
                <i class="bi bi-bell"></i>
                <span>1</span>
            </div>
            <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=200&q=80"
                 class="user-avatar">
        </div>

        <div class="search-box">
            <i class="bi bi-search"></i>
            <input placeholder="Search for Product or Brand">
            <i class="bi bi-mic"></i>
        </div>
    </div>
</div>
<!-- SIDEBAR -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<div id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h5>Menu</h5>
        <button id="closeSidebar" class="btn-close"></button>
    </div>
    <ul class="sidebar-menu">
        <li><a href="#">Home</a></li>
        <li><a href="#">Categories</a></li>
        <li><a href="#">Deals</a></li>
        <li><a href="#">Orders</a></li>
        <li><a href="#">Account</a></li>
        <li><a href="#">Settings</a></li>
    </ul>
</div>

<div class="app">

    <!-- CATEGORIES -->
    <div class="categories">
        <div class="category active">All</div>
        <div class="category">Electronics</div>
        <div class="category">Fashion</div>
        <div class="category">Home & Living</div>
        <div class="category">Groceries</div>
    </div>

</div>

<!-- BOTTOM NAV -->
<div class="bottom-nav">
    <a class="active"><i class="bi bi-house"></i><br>Home</a>
    <a><i class="bi bi-grid"></i><br>Category</a>
    <a><i class="bi bi-bag"></i><br>Orders</a>
    <a><i class="bi bi-person"></i><br>Account</a>
    <a><i class="bi bi-three-dots"></i><br>More</a>
</div>
<!-- Bootstrap JS (required for carousel) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const openBtn = document.getElementById('openSidebar');
const closeBtn = document.getElementById('closeSidebar');

// Open sidebar
openBtn.addEventListener('click', () => {
    sidebar.classList.add('open');
    overlay.classList.add('active');
});

// Close sidebar
closeBtn.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
});

// Close when clicking outside
overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
});
</script>

</body>
</html>
