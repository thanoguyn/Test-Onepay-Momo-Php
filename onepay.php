<?php
/**
 * TÀI LIỆU TÍCH HỢP CỔNG THANH TOÁN ONEPAY - BẢN TEST PHP (AJAX, POPUP & TAILWIND CSS)
 * Cấu trúc gộp 1 file All-in-One.
 */
header('Content-type: text/html; charset=utf-8');

$action = isset($_GET['action']) ? $_GET['action'] : 'ui';

// Hàm tính toán Hash chuẩn OnePAY (Lọc key vpc_, user_, loại rỗng, ksort, pack Hex Secret)
function generateOnePayHash($params, $secure_secret) {
    ksort($params);
    $stringHashData = "";
    foreach ($params as $key => $value) {
        if ($key != "vpc_SecureHash" && strlen($value) > 0 && (substr($key, 0, 4) == "vpc_" || substr($key, 0, 5) == "user_")) {
            $stringHashData .= $key . "=" . $value . "&";
        }
    }
    $stringHashData = rtrim($stringHashData, "&");
    return strtoupper(hash_hmac('SHA256', $stringHashData, pack('H*', $secure_secret)));
}

// Hàm gửi Request
function execPostRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded')
    );
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// ---------------------------
// 1. INIT PAYMENT (AJAX)
// ---------------------------
if ($action === 'init') {
    header('Content-Type: application/json');
    $reqData = $_POST;
    $endpoint = $reqData['endpoint_pay'] ?? '';
    $secretKey = $reqData['global_secretKey'] ?? '';
    
    // Loại bỏ các trường không cần thiết gửi sang OnePAY
    unset($reqData['endpoint_pay']);
    unset($reqData['global_secretKey']);
    
    $reqData['vpc_SecureHash'] = generateOnePayHash($reqData, $secretKey);
    $redirectUrl = $endpoint . "?" . http_build_query($reqData);
    
    echo json_encode(['payUrl' => $redirectUrl]);
    exit;
}

// ---------------------------
// 2. QUERY DR (AJAX)
// ---------------------------
if ($action === 'query') {
    header('Content-Type: application/json');
    $endpoint = $_GET['endpoint_query'] ?? '';
    $secretKey = $_POST['secretKey'] ?? ''; 
    
    $queryData = $_GET;
    unset($queryData['action']);
    unset($queryData['endpoint_query']);
    
    $queryData['vpc_SecureHash'] = generateOnePayHash($queryData, $secretKey);
    
    $queryResponseRaw = execPostRequest($endpoint, http_build_query($queryData));
    
    parse_str($queryResponseRaw, $queryResponseArr);
    echo json_encode($queryResponseArr);
    exit;
}

// ---------------------------
// 3. VERIFY SIGNATURE (RESULT / IPN)
// ---------------------------
if ($action === 'verify') {
    header('Content-Type: application/json');
    $secretKey = $_POST['secretKey'] ?? '';
    $req = $_GET; 
    
    ksort($req);
    $stringHashData = "";
    foreach ($req as $key => $value) {
        if ($key != "vpc_SecureHash" && strlen($value) > 0 && (substr($key, 0, 4) == "vpc_" || substr($key, 0, 5) == "user_")) {
            $stringHashData .= $key . "=" . $value . "&";
        }
    }
    $stringHashData = rtrim($stringHashData, "&");
    $partnerSignature = strtoupper(hash_hmac('SHA256', $stringHashData, pack('H*', $secretKey)));
    
    $m2signature = strtoupper($req['vpc_SecureHash'] ?? '');
    $isValid = ($m2signature === $partnerSignature);

    echo json_encode([
        'isValid' => $isValid,
        'partnerSignature' => $partnerSignature,
        'rawHash' => $stringHashData
    ]);
    exit;
}

// ---------------------------
// 4. GIAO DIỆN RETURN & IPN
// ---------------------------
if ($action === 'return' || $action === 'ipn') {
    // Nếu là luồng IPN thật từ OnePAY bắn về (backend call), xử lý logic IPN ở đây.
    // Hiện tại đang ưu tiên hiển thị UI để test.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || (!isset($_GET['vpc_TxnResponseCode']) && empty($_GET))) {
         // Xử lý IPN ngầm ở đây nếu cần, theo tài liệu OnePAY phải trả về chuỗi confirm[cite: 6]
         if(isset($_GET['vpc_Command'])) {
             // Validate chữ ký và cập nhật DB ...
             echo "responsecode=1&desc=confirm-success"; //[cite: 6]
             exit;
         }
    }

    $pageTitle = $action === 'return' ? 'Kết quả thanh toán (OnePAY Return)' : 'Kết quả IPN (Webhook)';
    ?>
    <!DOCTYPE html>
    <html lang="vi">
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
                        Nhập <b>Secure Secret</b> để tự xác thực chữ ký (Các tham số của OnePAY được lấy trực tiếp từ URL bên trái, Key được gửi chìm qua Post).
                    </p>

                    <form id="verifyForm" onsubmit="submitVerifyAjax(event)" class="bg-white p-5 rounded border border-gray-200 shadow-sm mb-6">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Secure Secret (Hex)</label>
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
                    
                    let vpcResCode = searchParams.get('vpc_TxnResponseCode');
                    if(json.isValid) {
                        let statusMsg = vpcResCode === '0' ? 'Giao dịch Thành Công (vpc_TxnResponseCode = 0)' : ('Giao dịch Thất bại (Mã lỗi: ' + vpcResCode + ')');
                        resultDiv.innerHTML = `<div class="bg-green-100 border-l-4 border-green-500 text-green-800 p-4 rounded shadow-sm"><strong>[HỢP LỆ] </strong>${statusMsg}</div>`;
                    } else {
                        resultDiv.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-800 p-4 rounded shadow-sm"><strong>[LỖI BẢO MẬT]</strong> Chữ ký không khớp (Sai SecretKey hoặc dữ liệu bị sửa).</div>`;
                    }
                    
                    document.getElementById('debugInfo').innerHTML = `
                        <div class="bg-gray-50 border p-4 rounded-lg">
                            <div class="mb-3"><b class="text-gray-700 text-sm uppercase">Chuỗi Hash (Raw Data):</b><pre class="bg-gray-800 text-green-400 p-3 mt-1 rounded text-sm overflow-x-auto whitespace-pre-wrap break-all">${json.rawHash}</pre></div>
                            <div class="mb-3"><b class="text-gray-700 text-sm uppercase">Partner Signature (Tính toán):</b><pre class="bg-white border p-2 mt-1 rounded text-sm break-all font-mono text-gray-800">${json.partnerSignature}</pre></div>
                            <div><b class="text-gray-700 text-sm uppercase">OnePAY Signature (Từ URL):</b><pre class="bg-white border p-2 mt-1 rounded text-sm break-all font-mono text-gray-800">${searchParams.get('vpc_SecureHash') || ''}</pre></div>
                        </div>
                    `;
                } catch(err) {
                    alert('Lỗi xác thực');
                }
                btn.innerText = "Xác thực kết quả";
                btn.disabled = false;
            }

            window.onload = function() {
                let conf = JSON.parse(localStorage.getItem('onepayConfig') || '{}');
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
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OnePAY Sandbox All-in-One</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans p-4 md:p-8">

<div class="max-w-7xl mx-auto">
    <!-- GLOBAL CONFIGURATION -->
    <div class="bg-gray-900 shadow-md rounded-lg overflow-hidden border border-gray-700 mb-6">
        <div class="bg-gray-800 text-yellow-400 px-6 py-4 font-bold text-lg flex items-center">
            <span class="mr-2">🔑</span> Cấu hình Secure Secret chung (Global)
        </div>
        <div class="p-6 bg-gray-900">
            <label class="block text-sm font-bold text-gray-300 mb-2">Secure Secret <span class="text-xs font-normal text-gray-400">(Tất cả các tính năng tạo/kiểm tra chữ ký bên dưới đều dùng chung key này)</span></label>
            <input type="text" id="global_secretKey" class="config-item w-full border border-gray-600 bg-gray-800 text-white rounded px-3 py-2 font-mono focus:ring-yellow-500 focus:border-yellow-500" placeholder="VD: 6D0870CDE5F24F34F3915FB0045120DB" />
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Endpoint URL</label>
                            <input type="text" id="endpoint_pay" name="endpoint_pay" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_Merchant</label>
                                <input type="text" id="vpc_Merchant" name="vpc_Merchant" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_AccessCode</label>
                                <input type="text" id="vpc_AccessCode" name="vpc_AccessCode" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_Version & Command</label>
                                <div class="flex gap-2">
                                    <input type="text" id="vpc_Version" name="vpc_Version" class="config-item w-1/3 border border-gray-300 rounded px-3 py-2 text-center" title="Version" />
                                    <input type="text" id="vpc_Command" name="vpc_Command" class="config-item w-2/3 border border-gray-300 rounded px-3 py-2" title="Command" />
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 text-blue-600 font-bold">vpc_MerchTxnRef</label>
                                <div class="flex relative shadow-sm">
                                    <input type="text" id="vpc_MerchTxnRef" name="vpc_MerchTxnRef" class="w-full border border-blue-300 bg-blue-50 rounded-l px-3 py-2 focus:ring-blue-500" title="Mã đơn hàng" />
                                    <button type="button" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-2 border border-l-0 border-blue-300 rounded-r transition" onclick="generateNewTxnRef()" title="Sinh mã mới">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_Amount <span class="text-xs font-normal text-gray-400">(x100)</span></label>
                                <input type="text" id="vpc_Amount" name="vpc_Amount" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_OrderInfo</label>
                                <input type="text" id="vpc_OrderInfo" name="vpc_OrderInfo" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_Locale</label>
                                <input type="text" id="vpc_Locale" name="vpc_Locale" class="config-item w-full border border-gray-300 rounded px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_TicketNo (IP)</label>
                                <input type="text" id="vpc_TicketNo" name="vpc_TicketNo" class="config-item w-full border border-gray-300 rounded px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_Currency</label>
                                <input type="text" id="vpc_Currency" name="vpc_Currency" class="config-item w-full border border-gray-300 rounded px-3 py-2" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_ReturnURL</label>
                                <input type="text" id="vpc_ReturnURL" name="vpc_ReturnURL" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_CallbackURL (IPN)</label>
                                <input type="text" id="vpc_CallbackURL" name="vpc_CallbackURL" class="config-item w-full border border-gray-300 rounded px-3 py-2 focus:ring-blue-500" title="URL xử lý cập nhật giao dịch backend" />
                            </div>
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
                    <p class="text-sm text-gray-500 mb-4">Dán toàn bộ Link Return/IPN của OnePAY vào đây. Hệ thống tự nhận diện các tham số <code>vpc_</code>, <code>user_</code>, tự sắp xếp Alphabet, giải mã Key và tính Signature.</p>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Phân tích URL OnePAY (Dán URL vào đây)</label>
                        <textarea id="tool_url" rows="3" class="w-full border border-indigo-300 bg-indigo-50 rounded px-3 py-2 font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="https://.../?vpc_TxnResponseCode=0&vpc_SecureHash=..." oninput="parseUrlAndCalculate()"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Chuỗi tham số (Raw Data - Đã Sort Alphabet)</label>
                        <textarea id="tool_rawStr" rows="4" class="w-full border border-gray-300 bg-gray-50 rounded px-3 py-2 font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="vpc_Amount=10000000&vpc_Command=pay..." oninput="calculateToolHash()"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Signature cần so sánh (Tự bóc tách từ URL)</label>
                        <input type="text" id="tool_targetSig" class="w-full border border-gray-300 rounded px-3 py-2 font-mono text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Signature..." oninput="calculateToolHash()">
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded border border-gray-200 relative">
                        <div class="text-sm font-bold text-gray-700 mb-1">Mã Hash tính toán được (SHA256):</div>
                        <div id="tool_calculatedSig" class="font-mono text-sm break-all text-gray-600 min-h-[1.5rem]">...</div>
                        
                        <div id="tool_badge" class="mt-3 hidden px-3 py-1.5 rounded font-bold text-sm w-fit border shadow-sm"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: URL TEST & QUERY -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- QUERY DR TRANSACTION -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="bg-gray-800 text-white px-6 py-4 font-bold text-lg">Truy vấn QueryDR</div>
                <div class="p-6">
                    <form id="queryForm" onsubmit="submitQueryAjax(event)">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Query Endpoint URL</label>
                            <input type="text" id="endpoint_query" name="endpoint_query" class="config-item w-full border border-gray-300 rounded px-3 py-2" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_User</label>
                                <input type="text" id="vpc_User" name="vpc_User" class="config-item w-full border border-gray-300 rounded px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">vpc_Password</label>
                                <input type="text" id="vpc_Password" name="vpc_Password" class="config-item w-full border border-gray-300 rounded px-3 py-2" />
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1 text-blue-600 font-bold">vpc_MerchTxnRef cần truy vấn</label>
                            <div class="flex">
                                <input type="text" id="q_MerchTxnRef" name="vpc_MerchTxnRef" class="w-full border border-blue-300 rounded-l px-3 py-2 bg-blue-50 focus:ring-blue-500" />
                                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 border border-l-0 border-gray-300 rounded-r transition whitespace-nowrap" onclick="document.getElementById('q_MerchTxnRef').value = document.getElementById('vpc_MerchTxnRef').value">
                                    Copy từ Cột Trái
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded font-medium shadow transition" id="btnQuery">Gửi truy vấn QueryDR (AJAX)</button>
                    </form>
                </div>
            </div>

            <!-- URL MOCK -->
            <div id="mockUrlsSection" class="hidden bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="bg-teal-600 text-white px-6 py-4 font-bold text-lg">Giả lập URL Trả về (Return / IPN)</div>
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
            <h3 class="text-xl font-bold text-gray-800">Kết quả phản hồi (JSON)</h3>
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
        global_secretKey: "6D0870CDE5F24F34F3915FB0045120DB",
        endpoint_pay: "https://mtf.onepay.vn/paygate/vpcpay.op",
        endpoint_query: "https://mtf.onepay.vn/msp/api/v1/vpc/invoices/queries",
        vpc_Version: "2",
        vpc_Command: "pay",
        vpc_AccessCode: "6BEB2546",
        vpc_Merchant: "TESTONEPAY",
        vpc_Locale: "vn",
        vpc_Amount: "10000000",
        vpc_OrderInfo: "Don hang test",
        vpc_TicketNo: "127.0.0.1",
        vpc_Currency: "VND",
        vpc_ReturnURL: baseUrl + "?action=return",
        vpc_CallbackURL: baseUrl + "?action=ipn",
        vpc_User: "op01",
        vpc_Password: "op123456"
    };

    function generateNewTxnRef() {
        document.getElementById('vpc_MerchTxnRef').value = "TEST_" + Math.floor(Date.now() / 1000).toString();
        // Cập nhật giá trị bên ô query
        document.getElementById('q_MerchTxnRef').value = document.getElementById('vpc_MerchTxnRef').value;
    }

    function loadConfig() {
        let stored = localStorage.getItem('onepayConfig');
        let config;
        
        // Nếu localStorage trống, khởi tạo luôn giá trị mặc định vào localStorage
        if (!stored) {
            config = defaultConfig;
            localStorage.setItem('onepayConfig', JSON.stringify(config));
        } else {
            config = JSON.parse(stored);
        }
        
        if (!config.vpc_ReturnURL || config.vpc_ReturnURL.indexOf('?action=return') === -1) {
            config.vpc_ReturnURL = baseUrl + "?action=return";
        }
        if (!config.vpc_CallbackURL || config.vpc_CallbackURL.indexOf('?action=ipn') === -1) {
            config.vpc_CallbackURL = baseUrl + "?action=ipn";
        }

        // Tự sinh Mã giao dịch (vpc_MerchTxnRef) mỗi lần Load
        generateNewTxnRef();

        document.querySelectorAll('.config-item').forEach(el => {
            if (config[el.id] !== undefined) {
                el.value = config[el.id];
            }
        });
        document.getElementById('q_MerchTxnRef').value = document.getElementById('vpc_MerchTxnRef').value;
    }

    function saveConfig() {
        let config = {};
        document.querySelectorAll('.config-item').forEach(el => { 
            config[el.id] = el.value; 
        });
        localStorage.setItem('onepayConfig', JSON.stringify(config));
        
        if(document.getElementById('tool_url').value || document.getElementById('tool_rawStr').value) {
            calculateToolHash();
        }
        if(!document.getElementById('mockUrlsSection').classList.contains('hidden')){
            generateTestUrls();
        }
    }

    function resetConfig() {
        if(confirm("Xác nhận reset toàn bộ thông số về mặc định?")) {
            localStorage.removeItem('onepayConfig');
            loadConfig();
            document.getElementById('mockUrlsSection').classList.add('hidden');
            document.getElementById('btnCopyPayUrl').dataset.url = "";
        }
    }

    // Logic OnePAY HASH client-side
    function generateOnePaySignature(params, secretHexStr) {
        // Lọc key
        let keys = Object.keys(params).filter(k => k !== 'vpc_SecureHash' && params[k] !== '' && (k.startsWith('vpc_') || k.startsWith('user_'))).sort();
        let rawStr = keys.map(k => k + '=' + params[k]).join('&');
        
        // OnePAY yêu cầu decode HEX cái SecretKey
        let keyHex = CryptoJS.enc.Hex.parse(secretHexStr);
        let hash = CryptoJS.HmacSHA256(rawStr, keyHex).toString(CryptoJS.enc.Hex).toUpperCase();
        
        return { rawStr, hash };
    }

    // JS bóc tách URL
    function parseUrlAndCalculate() {
        let urlStr = document.getElementById('tool_url').value.trim();
        if (!urlStr) return;
        
        try {
            let search = urlStr.includes('?') ? urlStr.substring(urlStr.indexOf('?')) : (urlStr.startsWith('http') ? '' : '?' + urlStr);
            let params = new URLSearchParams(search);
            
            if(params.has('vpc_SecureHash')) {
                document.getElementById('tool_targetSig').value = params.get('vpc_SecureHash');
                params.delete('vpc_SecureHash');
            }
            params.delete('action'); 
            
            let objParams = {};
            for (let [k, v] of params.entries()) objParams[k] = v;
            
            let res = generateOnePaySignature(objParams, document.getElementById('global_secretKey').value);
            document.getElementById('tool_rawStr').value = res.rawStr;
            calculateToolHash();
        } catch(e) { console.error("Lỗi parse URL"); }
    }

    function calculateToolHash() {
        let rawStr = document.getElementById('tool_rawStr').value;
        let secKey = document.getElementById('global_secretKey').value;
        let targetSig = document.getElementById('tool_targetSig').value.trim().toUpperCase();
        let calcDiv = document.getElementById('tool_calculatedSig');
        let badge = document.getElementById('tool_badge');

        if (!rawStr || !secKey) {
            calcDiv.innerText = "..."; badge.classList.add('hidden'); return;
        }

        let keyHex = CryptoJS.enc.Hex.parse(secKey);
        let calculated = CryptoJS.HmacSHA256(rawStr, keyHex).toString(CryptoJS.enc.Hex).toUpperCase();
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
        } else { badge.classList.add('hidden'); }
    }

    async function submitInitAjax(e) {
        e.preventDefault();
        let btn = document.getElementById('btnInit');
        let origText = btn.innerText;
        btn.innerText = "Đang kết nối...";
        btn.disabled = true;

        let formData = new FormData(document.getElementById('initForm'));
        formData.append('global_secretKey', document.getElementById('global_secretKey').value);
        formData.append('vpc_MerchTxnRef', document.getElementById('vpc_MerchTxnRef').value);
        
        try {
            let res = await fetch('?action=init', { method: 'POST', body: formData });
            let json = await res.json();
            
            if (json.payUrl) {
                window.open(json.payUrl, '_blank');
                document.getElementById('btnCopyPayUrl').dataset.url = json.payUrl;
                document.getElementById('mockUrlsSection').classList.remove('hidden');
                generateTestUrls(); 
            }
        } catch (err) { alert("Lỗi kết nối."); }
        btn.innerText = origText;
        btn.disabled = false;
    }

    function copyPayUrl() {
        let url = document.getElementById('btnCopyPayUrl').dataset.url;
        if(!url) { alert("Vui lòng khởi tạo thanh toán trước!"); return; }
        navigator.clipboard.writeText(url).then(() => {
            let btn = document.getElementById('btnCopyPayUrl');
            let orig = btn.innerText; btn.innerText = "Đã Copy!";
            setTimeout(() => btn.innerText = orig, 1500);
        });
    }

    function buildMockUrl(type, isSuccess) {
        let params = {
            vpc_Command: "pay",
            vpc_Locale: document.getElementById('vpc_Locale').value,
            vpc_MerchTxnRef: document.getElementById('vpc_MerchTxnRef').value,
            vpc_Merchant: document.getElementById('vpc_Merchant').value,
            vpc_OrderInfo: document.getElementById('vpc_OrderInfo').value,
            vpc_Amount: document.getElementById('vpc_Amount').value,
            vpc_TxnResponseCode: isSuccess ? "0" : "1",
            vpc_TransactionNo: Math.floor(Math.random() * 1000000).toString(),
            vpc_Message: isSuccess ? "Approved" : "Failed",
            vpc_ReceiptNo: "123456"
        };
        
        let sigData = generateOnePaySignature(params, document.getElementById('global_secretKey').value);
        params.vpc_SecureHash = sigData.hash;
        
        let targetUrl = type === 'return' ? document.getElementById('vpc_ReturnURL').value : document.getElementById('vpc_CallbackURL').value;
        let queryStr = new URLSearchParams(params).toString();
        return targetUrl + (targetUrl.includes('?') ? '&' : '?') + queryStr;
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
        navigator.clipboard.writeText(document.getElementById(inputId).value).then(() => {
            let orig = btnNode.innerText; btnNode.innerText = "Đã Copy!";
            btnNode.classList.replace('bg-gray-200', 'bg-green-200');
            setTimeout(() => { btnNode.innerText = orig; btnNode.classList.replace('bg-green-200', 'bg-gray-200'); }, 1500);
        });
    }

    async function submitQueryAjax(e) {
        e.preventDefault();
        let btn = document.getElementById('btnQuery');
        let origText = btn.innerText;
        btn.innerText = "Đang truy vấn...";
        btn.disabled = true;

        let queryParams = new URLSearchParams({
            action: 'query',
            endpoint_query: document.getElementById('endpoint_query').value,
            vpc_Command: "queryDR",
            vpc_Version: "2",
            vpc_Merchant: document.getElementById('vpc_Merchant').value,
            vpc_AccessCode: document.getElementById('vpc_AccessCode').value,
            vpc_MerchTxnRef: document.getElementById('q_MerchTxnRef').value,
            vpc_User: document.getElementById('vpc_User').value,
            vpc_Password: document.getElementById('vpc_Password').value
        });

        let formData = new FormData();
        formData.append('secretKey', document.getElementById('global_secretKey').value);

        try {
            let response = await fetch('?' + queryParams.toString(), { method: 'POST', body: formData });
            let result = await response.json();
            document.getElementById('queryResultContent').innerText = JSON.stringify(result, null, 4);
            document.getElementById('queryModal').classList.remove('hidden');
        } catch (error) { alert("Lỗi kết nối API Query"); } 
        finally { btn.innerText = origText; btn.disabled = false; }
    }

    function closeModal() { document.getElementById('queryModal').classList.add('hidden'); }

    document.querySelectorAll('.config-item').forEach(el => { el.addEventListener('input', saveConfig); });
    window.onload = loadConfig;
</script>
</body>
</html>
<?php } ?>
