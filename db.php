<?php
#Savienojuma izveide ar datubāzi:
$host = "localhost";
$username = "grobina1_jaunarajs";
$password = 'Nej$v3Hw0J7t';
$database = "grobina1_jaunarajs";

$conn = mysqli_connect($host, $username, $password, $database);

if(!$conn){
    die("Nav izveidots savienojums: " . mysqli_connect_error());
}else{
    #echo "Ir izveidots savienojums ar datubāzi";
}
?>