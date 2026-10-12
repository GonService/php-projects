<?php
/*
 * ============================================================================================
 *  INSECURE EXAMPLE - DO NOT DEPLOY. Kept exactly as "found" to teach what to fix.
 *  See ../README.md for the list of vulnerabilities and ../after/ for the refactored version.
 * ============================================================================================
 *
 * clientes.php - Cadastro de clientes
 * v1.3 - 2014 - alterado em 2019 (busca por nome, migrado para PDO)
 */
include 'guard.php';

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); // tira os avisos

$caminho = getenv('APP_DB') ? getenv('APP_DB') : dirname(__FILE__) . '/../var/app.sqlite';
$db = new PDO('sqlite:' . $caminho); // trocado de mysql_* para PDO em 2019, "sem mexer no resto"
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); // igual ao mysql_query antigo

// excluir cliente
if ($_GET['excluir']) {
    $db->exec("DELETE FROM clientes WHERE id = " . $_GET['excluir']);
    header("Location: clientes.php?msg=Cliente excluido");
    exit;
}

// salvar cliente novo
if ($_POST['acao'] == 'salvar') {
    $sql = "INSERT INTO clientes (nome, email, cidade, criado_em) VALUES ('" . $_POST['nome'] . "', '"
        . $_POST['email'] . "', '" . $_POST['cidade'] . "', '" . date('Y-m-d H:i:s') . "')";
    $db->exec($sql) or die("Erro ao salvar: " . print_r($db->errorInfo(), true) . "<br>SQL: " . $sql);
    $msg = "Cliente " . $_POST['nome'] . " cadastrado com sucesso!";
}

if ($_GET['msg']) $msg = $_GET['msg'];

$busca = $_GET['busca'];
$sql = "SELECT id, nome, email, cidade, criado_em FROM clientes WHERE nome LIKE '%" . $busca . "%' ORDER BY nome";
$res = $db->query($sql) or die("Erro na consulta: " . print_r($db->errorInfo(), true) . "<br>SQL: " . $sql);
?>
<html>
<head>
<title>Clientes</title>
<style>
body { font-family: Verdana, Arial; font-size: 12px; background: #eeeeee; }
table { background: #ffffff; }
td, th { border: 1px solid #999999; padding: 4px; }
th { background: #336699; color: #ffffff; }
.msg { background: #ffffcc; border: 1px solid #cccc00; padding: 5px; }
</style>
</head>
<body>
<h2>Cadastro de Clientes</h2>

<?php if ($msg) { ?><div class="msg"><?php echo $msg; ?></div><br><?php } ?>

<form method="get" action="clientes.php">
Buscar: <input type="text" name="busca" value="<?php echo $busca; ?>"> <input type="submit" value="OK">
</form>
<?php if ($busca) echo "<p>Resultados para: <b>" . $busca . "</b></p>"; ?>

<table cellspacing="0">
<tr><th>#</th><th>Nome</th><th>E-mail</th><th>Cidade</th><th>Desde</th><th></th></tr>
<?php
$i = 0;
while ($c = $res->fetch()) {
    $i++;
    echo "<tr>";
    echo "<td>" . $c['id'] . "</td>";
    echo "<td>" . $c['nome'] . "</td>";
    echo "<td>" . $c['email'] . "</td>";
    echo "<td>" . $c['cidade'] . "</td>";
    echo "<td>" . date('d/m/Y', strtotime($c['criado_em'])) . "</td>";
    echo "<td><a href=\"clientes.php?excluir=" . $c['id'] . "\" onclick=\"return confirm('Excluir?')\">excluir</a></td>";
    echo "</tr>";
}
if ($i == 0) echo "<tr><td colspan=6>Nenhum cliente encontrado</td></tr>";
?>
</table>

<h3>Novo cliente</h3>
<form method="post" action="clientes.php">
<input type="hidden" name="acao" value="salvar">
Nome: <input type="text" name="nome"><br>
E-mail: <input type="text" name="email"><br>
Cidade: <input type="text" name="cidade"><br>
<input type="submit" value="Salvar">
</form>

</body>
</html>
