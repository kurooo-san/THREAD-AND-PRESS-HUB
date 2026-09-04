<?php
require 'includes/config.php';

$products = [
    // 5 womens
    ['name'=>"Floral Summer Dress","description"=>"Light and breezy floral dress perfect for summer outings.","price"=>1899.00,"category"=>"dresses","image"=>"floral_dress.jpg","gender"=>"womens","available_colors"=>"Pink,White,Yellow","available_sizes"=>"S,M,L"],
    ['name'=>"Elegant Black Maxi Dress","description"=>"Elegant black maxi dress suitable for evening events.","price"=>2499.00,"category"=>"dresses","image"=>"black_dress.jpg","gender"=>"womens","available_colors"=>"Black,Maroon","available_sizes"=>"M,L,XL"],
    ['name'=>"Women's Graphic Tee","description"=>"Trendy graphic tee made from soft cotton.","price"=>499.00,"category"=>"t-shirts","image"=>"womens_graphic_tshirt.jpg","gender"=>"womens","available_colors"=>"White,Black,Blue","available_sizes"=>"XS,S,M,L"],
    ['name'=>"Women's Cotton Hoodie","description"=>"Comfortable cotton hoodie for all-day wear.","price"=>1299.00,"category"=>"hoodies","image"=>"women_hoodie.jpg","gender"=>"womens","available_colors"=>"Gray,Pink","available_sizes"=>"S,M,L,XL"],
    ['name'=>"Women's Slim Fit Jeans","description"=>"Stylish slim fit jeans designed for a flattering look.","price"=>1599.00,"category"=>"pants","image"=>"women_jeans.jpg","gender"=>"womens","available_colors"=>"Blue,Black","available_sizes"=>"S,M,L,XL"],

    // 5 mens
    ['name'=>"Men's Polo Shirt","description"=>"Classic polo shirt perfect for casual and semi-formal occasions.","price"=>699.00,"category"=>"t-shirts","image"=>"mens_polo.jpg","gender"=>"mens","available_colors"=>"Navy,Black,White","available_sizes"=>"S,M,L,XL,XXL"],
    ['name'=>"Men's Denim Jacket","description"=>"Durable denim jacket with a modern cut.","price"=>1999.00,"category"=>"hoodies","image"=>"mens_denim_jacket.jpg","gender"=>"mens","available_colors"=>"Blue,Black","available_sizes"=>"M,L,XL,XXL"],
    ['name'=>"Men's Jogger Pants","description"=>"Comfortable jogger pants with elastic waistband.","price"=>1299.00,"category"=>"pants","image"=>"mens_joggers.jpg","gender"=>"mens","available_colors"=>"Gray,Black","available_sizes"=>"M,L,XL,XXL"],
    ['name'=>"Men's Graphic Tee","description"=>"Stylish graphic tee made from premium fabric.","price"=>499.00,"category"=>"t-shirts","image"=>"mens_graphic_tshirt.jpg","gender"=>"mens","available_colors"=>"White,Black,Red","available_sizes"=>"S,M,L,XL"],
    ['name'=>"Men's Leather Belt","description"=>"Premium leather belt with classic buckle.","price"=>699.00,"category"=>"accessories","image"=>"mens_belt.jpg","gender"=>"mens","available_colors"=>"Black,Brown","available_sizes"=>"M,L,XL"],

    // 10 kids
    ['name'=>'Kids Cartoon Tee','description'=>'Fun cartoon print tee for kids.','price'=>299.00,'category'=>'t-shirts','image'=>'kids_cartoon_tshirt.jpg','gender'=>'kids','available_colors'=>'Yellow,Blue,Green','available_sizes'=>'XS,S,M'],
    ['name'=>'Kids Hoodie','description'=>'Cozy hoodie little ones will love to wear.','price'=>899.00,'category'=>'hoodies','image'=>'kids_hoodie.jpg','gender'=>'kids','available_colors'=>'Blue,Pink','available_sizes'=>'XS,S,M,L'],
    ['name'=>'Kids Denim Jeans','description'=>'Durable denim jeans perfect for playtime.','price'=>799.00,'category'=>'pants','image'=>'kids_jeans.jpg','gender'=>'kids','available_colors'=>'Blue','available_sizes'=>'XS,S,M,L'],
    ['name'=>'Kids Floral Dress','description'=>'Cute floral dress designed for kids.','price'=>599.00,'category'=>'dresses','image'=>'kids_floral_dress.jpg','gender'=>'kids','available_colors'=>'Pink,White','available_sizes'=>'XS,S,M'],
    ['name'=>'Kids Sports Shorts','description'=>'Lightweight shorts for active children.','price'=>399.00,'category'=>'pants','image'=>'kids_sports_shorts.jpg','gender'=>'kids','available_colors'=>'Black,Blue','available_sizes'=>'XS,S,M,L'],
    ['name'=>'Kids Sandals','description'=>'Comfortable sandals for everyday wear.','price'=>499.00,'category'=>'accessories','image'=>'kids_sandals.jpg','gender'=>'kids','available_colors'=>'Brown,Black','available_sizes'=>'30,32,34'],
    ['name'=>'Kids Backpack','description'=>'Colorful backpack suitable for school.','price'=>599.00,'category'=>'accessories','image'=>'kids_backpack.jpg','gender'=>'kids','available_colors'=>'Red,Blue,Green','available_sizes'=>'One Size'],
    ['name'=>'Kids Winter Coat','description'=>'Warm coat to keep kids cozy during cold seasons.','price'=>1299.00,'category'=>'hoodies','image'=>'kids_coat.jpg','gender'=>'kids','available_colors'=>'Red,Blue','available_sizes'=>'S,M,L'],
    ['name'=>'Kids Athletic Shoes','description'=>'Durable shoes for running and play.','price'=>899.00,'category'=>'accessories','image'=>'kids_shoes.jpg','gender'=>'kids','available_colors'=>'White,Black','available_sizes'=>'34,35,36'],
    ['name'=>'Kids Swim Trunks','description'=>'Bright swim trunks perfect for pool days.','price'=>499.00,'category'=>'pants','image'=>'kids_swim_trunks.jpg','gender'=>'kids','available_colors'=>'Blue,Green','available_sizes'=>'XS,S,M,L'],
];

$stmt = $conn->prepare("INSERT INTO products (name, description, price, category, image, status, gender, available_colors, available_sizes) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)");
$stmt->bind_param('ssdsssss', $name, $desc, $price, $cat, $img, $gender, $colors, $sizes);

foreach ($products as $p) {
    $name = $p['name'];
    $desc = $p['description'];
    $price = $p['price'];
    $cat = $p['category'];
    $img = $p['image'];
    $gender = $p['gender'];
    $colors = $p['available_colors'];
    $sizes = $p['available_sizes'];
    $stmt->execute();
}

$stmt->close();

echo "Sample products inserted successfully.";
?>