<?php
$n1 = '';
$n2 = '';
$n3 = '';
$n4 = '';
$n5 = '';
$n6 = '';
$n7 = '';
$n8 = '';
$soma = '';
$mult = '';
$sub = '';
$div = '';
//subindo teste o git
if (isset($_POST['btnsomar'])) {
    $n1 = $_POST['n1'];
    $n2 = $_POST['n2'];

    $soma = $n1 + $n2;
    
} elseif (isset($_POST['btnmult'])) {
    $n3 = $_POST['n3'];
    $n4 = $_POST['n4'];

    $mult = $n3 * $n4;
}elseif (isset ($_POST['btnsub'])) {
    $n5 = $_POST['n5'];
    $n6 = $_POST['n6'];
    
    $sub = $n5 - $n6; 
}elseif(isset ($_POST['btndiv'])){
    $n7 = $_POST['n7'];
    $n8 = $_POST['n8'];
    
    $div = $n7 / $n8;
}
?>

<h1>Adição</h1>

<form method="post" action="calculo.php">

    <label>Numero 1</label>
    <input type="text" name="n1" value="<?php echo $n1 ?>" />

    <label>Numero 2</label>
    <input type="text" name="n2" value="<?= $n2 ?>" />

    <button name="btnsomar">Somar</button>
    <input disabled value="<?= $soma ?>" />

</form>

<h1>Multiplicação</h1>
<form method="post" action="calculo.php">
    <label>Numero 1</label>
    <input type="text" name="n3" value="<?php echo $n3 ?>" />


    <label>Numero 2</label>
    <input type="text" name="n4" value="<?= $n4 ?>" />

    <button name="btnmult">Multiplicar</button>
    <input disabled value="<?= $mult ?>" />
</form>

<h1>Subtração</h1>
<form method="post" action="calculo.php">
    <label>Numero 1</label>
    <input type="text" name="n5" value="<?php echo $n5 ?>" />

    <label>Numero 2</label>
    <input type="text" name="n6" value="<?= $n6 ?>" />

    <button name="btnsub">Subtração</button>
    <input disabled value="<?= $sub ?>" />
</form>

<h1>Divisão</h1>
<form method="post" action="calculo.php">
    <label>Numero 1</label>
    <input type="text" name="n7" value="<?php echo $n7 ?> ">

    <label>Numero 2</label>
    <input type="text" name="n8" value="<?= $n8 ?>" />

    <button name="btndiv">Divisão</button>
    <input disabled value="<?= $div ?>" />
</form>
