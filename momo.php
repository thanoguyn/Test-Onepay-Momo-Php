<?php
header('Content-type: text/html; charset=utf-8');

$action = isset($_GET['action']) ? $_GET['action'] : 'ui';

function execPostRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data))
    );
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// ---------------------------
// 1. INIT PAYMENT (AJAX)
// ---------------------------
if ($action === 'init') {
    header('Content-Type: application/json');
    $endpoint = $_POST["endpoint"];
    $partnerCode = $_POST["partnerCode"];
    $accessKey = $_POST["accessKey"];
    $secretKey = $_POST["secretKey"]; // Đọc từ formData được append thêm
    $orderId = $_POST["orderId"];
    $orderInfo = $_POST["orderInfo"];
    $amount = $_POST["amount"];
    $ipnUrl = $_POST["ipnUrl"];
    $redirectUrl = $_POST["redirectUrl"];
    $extraData = $_POST["extraData"];

    $requestId = time() . "";
    $requestType = "captureWallet";
    
    $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
    $signature = hash_hmac("sha256", $rawHash, $secretKey);
    
    $data = array(
        'partnerCode' => $partnerCode,
        'partnerName' => "Test",
        "storeId" => "MomoTestStore",
        'requestId' => $requestId,
        'amount' => $amount,
        'orderId' => $orderId,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $redirectUrl,
        'ipnUrl' => $ipnUrl,
        'lang' => 'vi',
        'extraData' => $extraData,
        'requestType' => $requestType,
        'signature' => $signature
    );
    
    $result = execPostRequest($endpoint, json_encode($data));
    echo $result;
    exit;
}

// ---------------------------
// 2. QUERY TRANSACTION (AJAX)
// ---------------------------
if ($action === 'query') {
    header('Content-Type: application/json');
    
    $endpoint = $_GET["endpoint_query"] ?? '';
    $partnerCode = $_GET["partnerCode"] ?? '';
    $orderId = $_GET["orderId"] ?? '';
    
    $accessKey = $_POST["accessKey"] ?? '';
    $secretKey = $_POST["secretKey"] ?? '';
    
    $requestId = time()."";

    $rawHash = "accessKey=".$accessKey."&orderId=".$orderId."&partnerCode=".$partnerCode."&requestId=".$requestId;
    $signature = hash_hmac("sha256", $rawHash, $secretKey);

    $data = array(
        'partnerCode' => $partnerCode,
        'requestId' => $requestId,
        'orderId' => $orderId,
        'signature' => $signature,
        'lang' => 'vi'
    );
    $result = execPostRequest($endpoint, json_encode($data));
    echo $result;
    exit;
}

// ---------------------------
// 3. API VERIFY SIGNATURE (RESULT / IPN)
// ---------------------------
if ($action === 'verify') {
    header('Content-Type: application/json');
    
    $secretKey = $_POST['secretKey'] ?? '';
    $req = $_GET; 
    $accessKey = $req['accessKey'] ?? ''; // Lấy accessKey từ URL thay vì form
    
    $rawHash = "accessKey=" . $accessKey . 
               "&amount=" . ($req["amount"] ?? '') . 
               "&extraData=" . ($req["extraData"] ?? '') . 
               "&message=" . ($req["message"] ?? '') . 
               "&orderId=" . ($req["orderId"] ?? '') . 
               "&orderInfo=" . ($req["orderInfo"] ?? '') . 
               "&orderType=" . ($req["orderType"] ?? '') . 
               "&partnerCode=" . ($req["partnerCode"] ?? '') . 
               "&payType=" . ($req["payType"] ?? '') . 
               "&requestId=" . ($req["requestId"] ?? '') . 
               "&responseTime=" . ($req["responseTime"] ?? '') . 
               "&resultCode=" . ($req["resultCode"] ?? '') . 
               "&transId=" . ($req["transId"] ?? '');

    $partnerSignature = hash_hmac("sha256", $rawHash, $secretKey);
    $m2signature = $req['signature'] ?? '';
    $isValid = ($m2signature === $partnerSignature);

    echo json_encode([
        'isValid' => $isValid,
        'partnerSignature' => $partnerSignature,
        'rawHash' => $rawHash
    ]);
    exit;
}

// ---------------------------
// 4. GIAO DIỆN RETURN & IPN
// ---------------------------
if ($action === 'return' || $action === 'ipn') {
    $pageTitle = $action === 'return' ? 'Kết quả thanh toán (Return URL)' : 'Kết quả IPN (Webhook)';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 py-10">
        <div class="max-w-6xl mx-auto p-6 bg-white rounded-lg shadow-xl">
            <h2 class="text-2xl font-bold mb-6 text-gray-800"><?php echo $pageTitle; ?></h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- CỘT TRÁI (1/3): Các params từ URL -->
                <div class="lg:col-span-1 bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-bold text-gray-700 mb-4 border-b pb-2">Tham số từ URL (GET)</h3>
                    <div class="space-y-3 text-sm h-[600px] overflow-y-auto pr-2">
                        <?php foreach($_GET as $key => $val): ?>
                            <?php if($key === 'action') continue; ?>
                            <div class="bg-white p-2 rounded shadow-sm border border-gray-100">
                                <div class="font-bold text-gray-700 text-xs uppercase mb-1"><?php echo htmlspecialchars($key); ?></div>
                                <div class="text-gray-600 break-all"><?php echo htmlspecialchars($val); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- CỘT PHẢI (2/3): Form xác thực -->
                <div class="lg:col-span-2">
                    <p class="text-sm text-gray-500 mb-4 bg-blue-50 p-3 rounded border border-blue-100">
                        Nhập <b>Secret Key</b> để tự xác thực chữ ký (Access Key và các tham số khác đã được nhận diện từ URL).
                    </p>

                    <form id="verifyForm" onsubmit="submitVerifyAjax(event)" class="bg-white p-5 rounded border border-gray-200 shadow-sm mb-6">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                            <input type="text" id="vk_secretKey" class="w-full border rounded px-3 py-2 font-mono text-sm focus:border-blue-500">
                        </div>
                        <button type="submit" id="btnVerify" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition">Xác thực kết quả</button>
                    </form>

                    <div id="verifyResult" class="hidden mb-6"></div>
                    <div id="debugInfo" class="space-y-4"></div>
                    
                    <div class="mt-8 border-t pt-4">
                        <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-6 rounded shadow transition">Đóng Tab Này</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            async function submitVerifyAjax(e) {
                if(e) e.preventDefault();
                let btn = document.getElementById('btnVerify');
                btn.innerText = "Đang xác thực...";
                btn.disabled = true;

                let searchParams = new URLSearchParams(window.location.search);
                searchParams.set('action', 'verify');

                let formData = new FormData();
                formData.append('secretKey', document.getElementById('vk_secretKey').value);

                try {
                    let res = await fetch('?' + searchParams.toString(), { method: 'POST', body: formData });
                    let json = await res.json();
                    
                    let resultDiv = document.getElementById('verifyResult');
                    resultDiv.classList.remove('hidden');
                    
                    if(json.isValid) {
                        let statusMsg = searchParams.get('resultCode') === '0' ? 'Giao dịch Thành Công (resultCode = 0)' : ('Giao dịch Thất bại - ' + searchParams.get('message'));
                        resultDiv.innerHTML = `<div class="bg-green-100 border-l-4 border-green-500 text-green-800 p-4 rounded shadow-sm"><strong>[HỢP LỆ] </strong>${statusMsg}</div>`;
                    } else {
                        resultDiv.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-800 p-4 rounded shadow-sm"><strong>[LỖI BẢO MẬT]</strong> Chữ ký không khớp (Sai SecretKey hoặc dữ liệu bị sửa).</div>`;
                    }
                    
                    document.getElementById('debugInfo').innerHTML = `
                        <div class="bg-gray-50 border p-4 rounded-lg">
                            <div class="mb-3"><b class="text-gray-700 text-sm uppercase">Chuỗi Hash (Raw Data):</b><pre class="bg-gray-800 text-green-400 p-3 mt-1 rounded text-sm overflow-x-auto whitespace-pre-wrap break-all">${json.rawHash}</pre></div>
                            <div class="mb-3"><b class="text-gray-700 text-sm uppercase">Partner Signature (Tính toán):</b><pre class="bg-white border p-2 mt-1 rounded text-sm break-all font-mono text-gray-800">${json.partnerSignature}</pre></div>
                            <div><b class="text-gray-700 text-sm uppercase">MoMo Signature (Từ URL):</b><pre class="bg-white border p-2 mt-1 rounded text-sm break-all font-mono text-gray-800">${searchParams.get('signature')}</pre></div>
                        </div>
                    `;
                } catch(err) {
                    alert('Lỗi xác thực');
                }
                btn.innerText = "Xác thực kết quả";
                btn.disabled = false;
            }

            window.onload = function() {
                let conf = JSON.parse(localStorage.getItem('momoConfig') || '{}');
                let skInput = document.getElementById('vk_secretKey');
                if(conf.global_secretKey) {
                    skInput.value = conf.global_secretKey;
                    submitVerifyAjax(); 
                }
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ---------------------------
// 5. MAIN UI INTERFACE
// ---------------------------
if ($action === 'ui') {
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $baseUrl = strtok($currentUrl, '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MoMo Sandbox All-in-One</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans p-4 md:p-8">

<div class="max-w-7xl mx-auto">
    <!-- GLOBAL CONFIGURATION -->
    <div class="bg-gray-900 shadow-md rounded-lg overflow-hidden border border-gray-700 mb-6">
        <div class="bg-gray-800 text-yellow-400 px-6 py-4 font-bold text-lg flex items-center">
            <span class="mr-2">🔑</span> Cấu hình Secret Key chung (Global)
        </div>
        <div class="p-6 bg-gray-900">
            <label class="block text-sm font-bold text-gray-300 mb-2">Secret Key <span class="text-xs font-normal text-gray-400">(Tất cả các tính năng tạo/kiểm tra chữ ký bên dưới đều dùng chung key này)</span></label>
            <input type="text" id="global_secretKey" class="config-item w-full border border-gray-600 bg-gray-800 text-white rounded px-3 py-2 font-mono focus:ring-yellow-500 focus:border-yellow-500" placeholder="Nhập Secret Key..." />
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- CỘT TRÁI: INIT PAYMENT & TOOL -->
        <div class="lg:col-span-7 space-y-6">
            
            <!-- 1. CARD KHỞI TẠO THANH TOÁN -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="bg-blue-600 text-white px-6 py-4 font-bold text-lg">Khởi tạo thanh toán (Init URL)</div>
                <div class="p-6">
                    <form id="initForm" onsubmit="submitInitAjax(event)">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Endpoint URL</label>
                            <input type="text" id="endpoint" name="endpoint" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">PartnerCode</label>
                                <input type="text" id="partnerCode" name="partnerCode" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">AccessKey</label>
                                <input type="text" id="accessKey" name="accessKey" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">OrderId</label>
                                <input type="text" id="orderId" name="orderId" class="w-full border border-gray-300 rounded px-3 py-2 bg-yellow-50 focus:ring-blue-500 focus:border-blue-500" title="Mã này tự sinh mới mỗi lần load trang" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                                <input type="text" id="amount" name="amount" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">OrderInfo</label>
                                <input type="text" id="orderInfo" name="orderInfo" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RedirectUrl</label>
                                <input type="text" id="redirectUrl" name="redirectUrl" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IpnUrl</label>
                                <input type="text" id="ipnUrl" name="ipnUrl" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">ExtraData</label>
                            <input type="text" id="extraData" name="extraData" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        
                        <div class="flex flex-wrap gap-2 justify-between items-center bg-gray-50 p-3 rounded border">
                            <div class="flex gap-2">
                                <button type="submit" id="btnInit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded font-medium shadow transition">Khởi tạo thanh toán</button>
                                <button type="button" id="btnCopyPayUrl" class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2 rounded font-medium shadow transition" onclick="copyPayUrl()">Copy URL thanh toán</button>
                            </div>
                            <button type="button" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-medium shadow transition" onclick="resetConfig()">Reset Toàn Bộ</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 2. CARD CÔNG CỤ KIỂM TRA HASH -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="bg-indigo-600 text-white px-6 py-4 font-bold text-lg">Công cụ bóc tách URL & Kiểm tra Hash</div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 mb-4">Dán toàn bộ Link Return/IPN vào đây. Hệ thống sẽ tự bóc tách tham số, tự động build chuỗi Raw Data theo Alphabet và đối chiếu với Signature trên URL.</p>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Phân tích URL MoMo trả về (Dán URL vào đây)</label>
                        <textarea id="tool_url" rows="3" class="w-full border border-indigo-300 bg-indigo-50 rounded px-3 py-2 font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="https://.../?partnerCode=...&signature=..." oninput="parseUrlAndCalculate()"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Chuỗi tham số (Raw Hash - Tự động sinh)</label>
                        <textarea id="tool_rawStr" rows="4" class="w-full border border-gray-300 bg-gray-50 rounded px-3 py-2 font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="accessKey=xxx&amount=10000..." oninput="calculateToolHash()"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Signature cần so sánh (Tự động bóc tách từ URL)</label>
                        <input type="text" id="tool_targetSig" class="w-full border border-gray-300 rounded px-3 py-2 font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Signature..." oninput="calculateToolHash()">
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded border border-gray-200 relative">
                        <div class="text-sm font-bold text-gray-700 mb-1">Mã Hash tính toán được từ Secret Key:</div>
                        <div id="tool_calculatedSig" class="font-mono text-sm break-all text-gray-600 min-h-[1.5rem]">...</div>
                        
                        <div id="tool_badge" class="mt-3 hidden px-3 py-1.5 rounded font-bold text-sm w-fit border shadow-sm"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: URL TEST & QUERY -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- QUERY TRANSACTION -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="bg-gray-800 text-white px-6 py-4 font-bold text-lg">Kiểm tra trạng thái (Query)</div>
                <div class="p-6">
                    <form id="queryForm" onsubmit="submitQueryAjax(event)">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Query Endpoint</label>
                            <input type="text" id="endpoint_query" name="endpoint_query" class="config-item w-full border border-gray-300 rounded px-3 py-2" />
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">OrderId cần kiểm tra</label>
                            <div class="flex">
                                <input type="text" id="q_orderId" name="orderId" class="w-full border border-gray-300 rounded-l px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 border border-l-0 border-gray-300 rounded-r transition whitespace-nowrap" onclick="document.getElementById('q_orderId').value = document.getElementById('orderId').value">
                                    Copy OrderId trái
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded font-medium shadow transition" id="btnQuery">Truy vấn giao dịch (AJAX)</button>
                    </form>
                </div>
            </div>

            <!-- URL MOCK -->
            <div id="mockUrlsSection" class="hidden bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="bg-teal-600 text-white px-6 py-4 font-bold text-lg">Giả lập Test URL (Return & IPN)</div>
                <div class="p-6 space-y-4">
                    <script>
                        function renderMockRow(id, label, colorClass) {
                            document.write(`
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">${label}</label>
                                    <div class="flex gap-2">
                                        <input type="text" id="${id}_input" class="w-full border border-gray-300 bg-gray-50 rounded px-3 py-2 text-sm text-gray-500" readonly />
                                        <button onclick="copyToClipboard('${id}_input', this)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded transition whitespace-nowrap text-sm font-medium">Copy</button>
                                        <a id="${id}_link" href="#" target="_blank" class="${colorClass} text-white px-3 py-2 rounded transition whitespace-nowrap text-sm font-medium flex items-center shadow">Mở Tab</a>
                                    </div>
                                </div>
                            `);
                        }
                    </script>
                    <script>renderMockRow('test_ret_success', 'Return URL - Thành công', 'bg-green-500 hover:bg-green-600');</script>
                    <script>renderMockRow('test_ret_fail', 'Return URL - Thất bại', 'bg-red-500 hover:bg-red-600');</script>
                    <hr class="border-gray-200">
                    <script>renderMockRow('test_ipn_success', 'IPN URL - Thành công', 'bg-green-500 hover:bg-green-600');</script>
                    <script>renderMockRow('test_ipn_fail', 'IPN URL - Thất bại', 'bg-red-500 hover:bg-red-600');</script>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL QUERY RESULT -->
<div id="queryModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="text-xl font-bold text-gray-800">Kết quả truy vấn</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-800 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto flex-grow">
            <pre id="queryResultContent" class="bg-gray-900 text-green-400 p-4 rounded text-sm whitespace-pre-wrap break-all"></pre>
        </div>
        <div class="px-6 py-4 border-t bg-gray-50 text-right">
            <button onclick="closeModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded shadow transition">Đóng</button>
        </div>
    </div>
</div>

<script>
    const baseUrl = "<?php echo $baseUrl; ?>";
    const defaultConfig = {
        endpoint: "https://test-payment.momo.vn/v2/gateway/api/create",
        partnerCode: "MOMOBKUN20180529",
        accessKey: "klm05TvNBzhg7h7j",
        global_secretKey: "at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa",
        amount: "10000",
        orderInfo: "Thanh toán qua MoMo Sandbox",
        redirectUrl: baseUrl + "?action=return",
        ipnUrl: baseUrl + "?action=ipn",
        extraData: "",
        endpoint_query: "https://test-payment.momo.vn/v2/gateway/api/query"
    };

    function loadConfig() {
        let stored = localStorage.getItem('momoConfig');
        if(!stored) {
            localStorage.setItem('momoConfig', JSON.stringify(defaultConfig));
        }
        let config = stored ? JSON.parse(stored) : defaultConfig;
        
        if (!config.redirectUrl || config.redirectUrl.indexOf('?action=return') === -1) {
            config.redirectUrl = baseUrl + "?action=return";
            config.ipnUrl = baseUrl + "?action=ipn";
        }

        document.getElementById('orderId').value = Math.floor(Date.now() / 1000).toString();

        document.querySelectorAll('.config-item').forEach(el => {
            if (el.id !== 'orderId' && config[el.id] !== undefined) {
                el.value = config[el.id];
            }
        });
        document.getElementById('q_orderId').value = document.getElementById('orderId').value;
    }

    function saveConfig() {
        let config = {};
        document.querySelectorAll('.config-item').forEach(el => { 
            if (el.id !== 'orderId') config[el.id] = el.value; 
        });
        localStorage.setItem('momoConfig', JSON.stringify(config));
        
        // Auto trigger tool check nếu đang có data
        if(document.getElementById('tool_url').value || document.getElementById('tool_rawStr').value) {
            calculateToolHash();
        }

        if(!document.getElementById('mockUrlsSection').classList.contains('hidden')){
            generateTestUrls();
        }
    }

    function resetConfig() {
        if(confirm("Xác nhận reset toàn bộ thông số về mặc định?")) {
            localStorage.removeItem('momoConfig');
            loadConfig();
            document.getElementById('mockUrlsSection').classList.add('hidden');
            document.getElementById('btnCopyPayUrl').dataset.url = "";
        }
    }

    // Logic bóc tách URL cho Tool check Hash
    function parseUrlAndCalculate() {
        let urlStr = document.getElementById('tool_url').value.trim();
        if (!urlStr) return;
        
        try {
            let search = urlStr.includes('?') ? urlStr.substring(urlStr.indexOf('?')) : (urlStr.startsWith('http') ? '' : '?' + urlStr);
            let params = new URLSearchParams(search);
            
            // Xoá signature và đem xuống ô so sánh
            if(params.has('signature')) {
                document.getElementById('tool_targetSig').value = params.get('signature');
                params.delete('signature');
            }
            
            // Xoá action vì đây là param điều hướng riêng của web mình
            params.delete('action'); 
            
            // Sắp xếp các params theo Alphabet & tạo chuỗi
            let keys = Array.from(params.keys()).sort();
            let rawParts = [];
            for (let k of keys) {
                rawParts.push(k + "=" + params.get(k));
            }
            
            document.getElementById('tool_rawStr').value = rawParts.join('&');
            calculateToolHash();
        } catch(e) {
            console.error("Lỗi parse URL", e);
        }
    }

    // Logic tính toán cho Tool check Hash
    function calculateToolHash() {
        let rawStr = document.getElementById('tool_rawStr').value;
        let secKey = document.getElementById('global_secretKey').value;
        let targetSig = document.getElementById('tool_targetSig').value.trim();
        let calcDiv = document.getElementById('tool_calculatedSig');
        let badge = document.getElementById('tool_badge');

        if (!rawStr || !secKey) {
            calcDiv.innerText = "...";
            badge.classList.add('hidden');
            return;
        }

        let calculated = CryptoJS.HmacSHA256(rawStr, secKey).toString(CryptoJS.enc.Hex);
        calcDiv.innerText = calculated;

        if (targetSig) {
            badge.classList.remove('hidden');
            if (calculated === targetSig) {
                badge.innerText = "Trùng khớp (Hợp lệ)";
                badge.className = "mt-3 px-3 py-1.5 rounded font-bold text-sm w-fit border shadow-sm bg-green-100 text-green-700 border-green-300";
            } else {
                badge.innerText = "Không khớp (Sai)";
                badge.className = "mt-3 px-3 py-1.5 rounded font-bold text-sm w-fit border shadow-sm bg-red-100 text-red-700 border-red-300";
            }
        } else {
            badge.classList.add('hidden');
        }
    }

    async function submitInitAjax(e) {
        e.preventDefault();
        let btn = document.getElementById('btnInit');
        let origText = btn.innerText;
        btn.innerText = "Đang kết nối...";
        btn.disabled = true;

        let formData = new FormData(document.getElementById('initForm'));
        // Nối thêm secretKey từ Global Config
        formData.append('secretKey', document.getElementById('global_secretKey').value);
        
        try {
            let res = await fetch('?action=init', { method: 'POST', body: formData });
            let json = await res.json();
            
            if (json.payUrl) {
                window.open(json.payUrl, '_blank');
                document.getElementById('btnCopyPayUrl').dataset.url = json.payUrl;
                document.getElementById('mockUrlsSection').classList.remove('hidden');
                generateTestUrls(); 
            } else {
                alert("Lỗi từ MoMo:\n" + JSON.stringify(json, null, 2));
            }
        } catch (err) {
            alert("Lỗi kết nối hoặc cấu hình sai.");
        }
        btn.innerText = origText;
        btn.disabled = false;
    }

    function copyPayUrl() {
        let btn = document.getElementById('btnCopyPayUrl');
        let urlToCopy = btn.dataset.url;
        if(!urlToCopy) { alert("Vui lòng khởi tạo thanh toán trước!"); return; }
        navigator.clipboard.writeText(urlToCopy).then(() => {
            let orig = btn.innerText;
            btn.innerText = "Đã Copy URL!";
            setTimeout(() => btn.innerText = orig, 1500);
        });
    }

    function buildMockUrl(type, isSuccess) {
        let secretKey = document.getElementById('global_secretKey').value;
        let accessKey = document.getElementById('accessKey').value;
        let partnerCode = document.getElementById('partnerCode').value;
        let orderId = document.getElementById('orderId').value;
        let amount = document.getElementById('amount').value;
        let orderInfo = document.getElementById('orderInfo').value;
        let extraData = document.getElementById('extraData').value;
        
        let resultCode = isSuccess ? "0" : "1006";
        let message = isSuccess ? "Success" : "Failed";
        let transId = Math.floor(Date.now() / 1000).toString();
        let requestId = transId;
        let payType = "qr";
        let responseTime = transId;
        let orderType = "momo_wallet";

        let rawHash = "accessKey=" + accessKey + "&amount=" + amount + "&extraData=" + extraData + "&message=" + message + "&orderId=" + orderId + "&orderInfo=" + orderInfo + "&orderType=" + orderType + "&partnerCode=" + partnerCode + "&payType=" + payType + "&requestId=" + requestId + "&responseTime=" + responseTime + "&resultCode=" + resultCode + "&transId=" + transId;
        let signature = CryptoJS.HmacSHA256(rawHash, secretKey).toString(CryptoJS.enc.Hex);

        let params = new URLSearchParams({
            accessKey, amount, extraData, message, orderId, orderInfo, orderType, partnerCode, payType, requestId, responseTime, resultCode, transId, signature
        });

        let targetBaseUrl = type === 'return' ? document.getElementById('redirectUrl').value : document.getElementById('ipnUrl').value;
        return targetBaseUrl + (targetBaseUrl.includes('?') ? '&' : '?') + params.toString();
    }

    function updateUrlRow(id, url) {
        document.getElementById(id + '_input').value = url;
        document.getElementById(id + '_link').href = url;
    }

    function generateTestUrls() {
        updateUrlRow('test_ret_success', buildMockUrl('return', true));
        updateUrlRow('test_ret_fail', buildMockUrl('return', false));
        updateUrlRow('test_ipn_success', buildMockUrl('ipn', true));
        updateUrlRow('test_ipn_fail', buildMockUrl('ipn', false));
    }

    function copyToClipboard(inputId, btnNode) {
        let text = document.getElementById(inputId).value;
        navigator.clipboard.writeText(text).then(() => {
            let originalText = btnNode.innerText;
            btnNode.innerText = "Đã Copy!";
            btnNode.classList.replace('bg-gray-200', 'bg-green-200');
            btnNode.classList.replace('text-gray-700', 'text-green-800');
            setTimeout(() => {
                btnNode.innerText = originalText;
                btnNode.classList.replace('bg-green-200', 'bg-gray-200');
                btnNode.classList.replace('text-green-800', 'text-gray-700');
            }, 1500);
        });
    }

    async function submitQueryAjax(e) {
        e.preventDefault();
        let btn = document.getElementById('btnQuery');
        let originalText = btn.innerText;
        btn.innerText = "Đang truy vấn...";
        btn.disabled = true;

        let queryParams = new URLSearchParams({
            action: 'query',
            endpoint_query: document.getElementById('endpoint_query').value,
            partnerCode: document.getElementById('partnerCode').value,
            orderId: document.getElementById('q_orderId').value
        });

        let formData = new FormData();
        formData.append('accessKey', document.getElementById('accessKey').value);
        formData.append('secretKey', document.getElementById('global_secretKey').value);

        try {
            let response = await fetch('?' + queryParams.toString(), { method: 'POST', body: formData });
            let result = await response.json();
            document.getElementById('queryResultContent').innerText = JSON.stringify(result, null, 4);
            document.getElementById('queryModal').classList.remove('hidden');
        } catch (error) {
            alert("Lỗi kết nối API Query");
        } finally {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    function closeModal() { document.getElementById('queryModal').classList.add('hidden'); }

    document.querySelectorAll('.config-item').forEach(el => { el.addEventListener('input', saveConfig); });
    window.onload = loadConfig;
</script>
</body>
</html>
<?php } ?>
