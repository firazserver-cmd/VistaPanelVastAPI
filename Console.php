<?php
session_start();

/*
 * Available API packages.
 * Only ONE is loaded per request (both define the same class name).
 */
$packages = [
    "1" => [
        "label" => "Vista Panel Vast API",
        "file"  => "VistaPanelVastAPI.php"
    ]
];

/*
 * Selected package: POST value > saved session value > default (1).
 */
$selectedPackage = $_SESSION["vistapanel_package"] ?? "1";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["package"]) &&
    isset($packages[$_POST["package"]])
) {
    $selectedPackage = $_POST["package"];
}

if (!isset($packages[$selectedPackage])) {
    $selectedPackage = "1";
}

$_SESSION["vistapanel_package"] = $selectedPackage;

require_once __DIR__ . "/" . $packages[$selectedPackage]["file"];

$api = new VistaPanelVastApi();
$message = "";
$output = "";
$error = "";
$methods = [
    "setCpanelUrl" => ["url"],
    "FirstTimeLogin" => ["username", "password", "theme", "action"],
    "ApproveNotification" => [],
    "DisapproveNotification" => [],
    "ListDomainForParkedDomain" => [],
    "ListDomainForSubdomain" => [],
    "AccountDetails" => [],
    "login" => ["username", "password", "theme"],
    "setSession" => ["session", "username"],
    "createDatabase" => ["dbname"],
    "ListDatabases" => [],
    "deleteDatabase" => ["database"],
    "getPhpmyadminLink" => ["database"],
    "listDomains" => ["option"],
    "createRedirect" => ["domainname", "target"],
    "deleteRedirect" => ["domainname"],
    "showRedirect" => ["domainname"],
    "getPrivateKey" => ["domainname"],
    "getCertificate" => ["domainname"],
    "uploadPrivateKey" => ["domainname", "key", "csr"],
    "uploadCertificate" => ["domainname", "cert"],
    "deleteCertificate" => ["domainname"],
    "FetchSoftaculousLink" => [],
    "showErrorPage" => ["domainname", "option"],
    "UpdateErrorPages" => ["domainname", "v400", "v401", "v403", "v404", "v503"],
    "ShowPHPConfig" => ["domainname", "option"],
    "SetPHPConfig" => ["domainname", "displayerrors", "mbstringinput", "timezone"],
    "GetAccountStatistics" => ["option"],
    "FetchCNAMERecords" => [],
    "createCNAMErecord" => ["source", "domain", "dest"],
    "deleteCNAMErecord" => ["source"],
    "FetchMXRecords" => [],
    "createMXrecord" => ["domain", "server", "priority"],
    "DeleteMXRecord" => ["domain", "srv", "priority"],
    "FetchSPFRecords" => [],
    "CreateSPFRecord" => ["domain", "data"],
    "DeleteSPFRecord" => ["domain", "data"],
    "ChangeEmail" => ["newEmail", "confirmEmail"],
    "AddPasswordProtectionToFolder" => ["domainName", "folderName", "username", "password"],
    "createDNSRecord" => ["recordType", "domain", "data", "destination", "priority"],
    "AddDomain" => ["domainname", "option", "domain_selected"],
    "DeleteDomain" => ["domain", "type"],
    "FileManager" => [],
    "Logout" => []
];

/*
 * Restore the saved VistaPanel session.
 * Both session ID and username are required by setSession().
 */
if (
    isset($_SESSION["vistapanel_session"]) &&
    $_SESSION["vistapanel_session"] !== "" &&
    isset($_SESSION["vistapanel_username"]) &&
    $_SESSION["vistapanel_username"] !== ""
) {
    try {
        $api->setSession(
            $_SESSION["vistapanel_session"],
            $_SESSION["vistapanel_username"]
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$selectedMethod = $_POST["method"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $selectedMethod !== "") {
    try {
        if (!isset($methods[$selectedMethod])) {
            throw new Exception("Invalid method selected.");
        }

        $parameters = $methods[$selectedMethod];
        $arguments = [];

        foreach ($parameters as $parameter) {
            $arguments[] = $_POST["param_" . $parameter] ?? "";
        }

        $result = call_user_func_array(
            [$api, $selectedMethod],
            $arguments
        );

        /*
         * Successful login:
         * Save both the VistaPanel session ID and username.
         */
        if ($selectedMethod === "login") {
            $reflection = new ReflectionClass($api);

            $sessionProperty = $reflection->getProperty("vistapanelSession");
            $sessionProperty->setAccessible(true);

            $_SESSION["vistapanel_session"] =
                $sessionProperty->getValue($api);

            $_SESSION["vistapanel_username"] =
                $_POST["param_username"];
        }

        /*
         * Manually setting/restoring a session:
         * Save both values.
         */
        if ($selectedMethod === "setSession") {
            $_SESSION["vistapanel_session"] =
                $_POST["param_session"];

            $_SESSION["vistapanel_username"] =
                $_POST["param_username"];
        }

        /*
         * Logout:
         * Remove the saved VistaPanel session.
         * (Fixed: the method key is "Logout", not "logout".)
         */
        if ($selectedMethod === "Logout") {
            unset($_SESSION["vistapanel_session"]);
            unset($_SESSION["vistapanel_username"]);
        }

        if (is_array($result) || is_object($result)) {
            $output = print_r($result, true);
        } elseif ($result === true) {
            $output = "true";
        } elseif ($result === false) {
            $output = "false";
        } elseif ($result === null) {
            $output = "null";
        } else {
            $output = (string)$result;
        }

        $message = "Method executed successfully.";

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$methodCount = count($methods);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VistaPanel API Tester</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f8;
    color: #222;
}

.container {
    width: 95%;
    max-width: 900px;
    margin: 25px auto;
}

.card {
    background: #fff;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.08);
    margin-bottom: 20px;
}

h1 {
    margin-top: 0;
}

.method-count {
    color: #666;
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-top: 15px;
    margin-bottom: 6px;
}

select,
input,
textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 7px;
    font-size: 15px;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

button {
    margin-top: 20px;
    width: 100%;
    padding: 12px;
    border: 0;
    border-radius: 7px;
    background: #111;
    color: white;
    font-size: 16px;
    cursor: pointer;
}

button:hover {
    opacity: .85;
}

.success {
    padding: 12px;
    background: #e7f7ec;
    color: #146c2e;
    border-radius: 7px;
}

.error {
    padding: 12px;
    background: #fde8e8;
    color: #a61b1b;
    border-radius: 7px;
}

pre {
    white-space: pre-wrap;
    word-break: break-word;
    background: #111;
    color: #eee;
    padding: 15px;
    border-radius: 8px;
    overflow-x: auto;
}
</style>
</head>

<body>

<div class="container">

<div class="card">

    <h1>VistaPanel API Tester</h1>

    <div class="method-count">
        Total available methods:
        <strong><?php echo $methodCount; ?></strong>
    </div>

    <form method="post" id="apiForm">

        <label for="package">Select Package</label>

        <select name="package" id="package">

            <?php foreach ($packages as $key => $package): ?>

                <option
                    value="<?php echo htmlspecialchars($key); ?>"
                    <?php echo $selectedPackage === (string)$key ? "selected" : ""; ?>
                >
                    <?php echo htmlspecialchars($package["label"]); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <label for="method">Select Method</label>

        <select name="method" id="method" onchange="showParameters()">

            <option value="">
                -- Select Method --
            </option>

            <?php foreach ($methods as $method => $parameters): ?>

                <option
                    value="<?php echo htmlspecialchars($method); ?>"
                    <?php echo $selectedMethod === $method ? "selected" : ""; ?>
                >
                    <?php echo htmlspecialchars($method); ?>
                    (<?php echo count($parameters); ?>
                    parameter<?php echo count($parameters) === 1 ? "" : "s"; ?>)
                </option>

            <?php endforeach; ?>

        </select>

        <div id="parameters"></div>

        <button type="submit">
            Execute Method
        </button>

    </form>

</div>

<?php if ($message !== ""): ?>

<div class="card">

    <div class="success">
        <?php echo htmlspecialchars($message); ?><br>
        Package: <?php echo htmlspecialchars($packages[$selectedPackage]["label"]); ?>
    </div>

</div>

<?php endif; ?>

<?php if ($error !== ""): ?>

<div class="card">

    <div class="error">
        <strong>Error:</strong><br>
        <?php echo htmlspecialchars($error); ?>
    </div>

</div>

<?php endif; ?>

<?php if ($output !== ""): ?>

<div class="card">

    <h2>Output</h2>

    <pre><?php echo htmlspecialchars($output); ?></pre>

</div>

<?php endif; ?>

</div>

<script>

const methodParameters =
    <?php echo json_encode($methods); ?>;

function showParameters() {

    const method =
        document.getElementById("method").value;

    const container =
        document.getElementById("parameters");

    container.innerHTML = "";

    if (!method || !methodParameters[method]) {
        return;
    }

    const parameters =
        methodParameters[method];

    if (parameters.length === 0) {

        const text =
            document.createElement("p");

        text.textContent =
            "This method requires no parameters.";

        container.appendChild(text);

        return;
    }

    parameters.forEach(function(parameter) {

        const label =
            document.createElement("label");

        label.textContent =
            parameter;

        let input;

        if (
            parameter === "password" ||
            parameter === "key" ||
            parameter === "cert" ||
            parameter === "csr" ||
            parameter === "data"
        ) {

            input =
                document.createElement("textarea");

        } else {

            input =
                document.createElement("input");

            input.type = "text";
        }

        input.name =
            "param_" + parameter;

        input.placeholder =
            "Enter " + parameter;

        input.required = false;

        container.appendChild(label);
        container.appendChild(input);

    });
}

showParameters();

</script>

</body>
</html>
