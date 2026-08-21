<?php
if (isset($_POST["name"])) {
	$url = $_POST["name"];
    $TINYURL= file_get_contents('https://is.gd/create.php?format=simple&url='.$_POST["name"]); 
    if ($TINYURL == ""){
        $TINYURL= file_get_contents('https://tinyurl.com/api-create.php?url='.$_POST["name"]);
    }
    echo $TINYURL ; 
} else {
    echo "error!";
}
?>