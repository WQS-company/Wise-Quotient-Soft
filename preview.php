<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Shop – WhatsApp Order</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f6f8fa;
    font-family: "Segoe UI", sans-serif;
}
.product-card{
    border:none;
    border-radius:16px;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
    transition:transform .2s ease;
}
.product-card:hover{
    transform:translateY(-6px);
}
.product-img{
    height:220px;
    object-fit:cover;
}
.price{
    font-size:1.3rem;
    font-weight:700;
    color:#0d6efd;
}
.btn-whatsapp{
    background:#25D366;
    color:#fff;
    border:none;
    font-weight:600;
}
.btn-whatsapp:hover{
    background:#1ebe5d;
}
</style>
</head>

<body>

<div class="container py-5">
    <h2 class="text-center fw-bold mb-4">Our Products</h2>
    <div class="text-center py-5">
        <p class="text-muted">No products available at this time.</p>
    </div>
</div>



</body>
</html>
