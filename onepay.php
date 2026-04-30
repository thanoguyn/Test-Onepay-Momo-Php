<?php
/**
 * TÀI LIỆU TÍCH HỢP CỔNG THANH TOÁN ONEPAY - BẢN TEST PHP (AJAX, POPUP & TAILWIND CSS)
 * Môi trường: TEST
 */

// 1. CẤU HÌNH MÔI TRƯỜNG TEST (Theo mục III.1)
$SECURE_SECRET = "6D0870CDE5F24F34F3915FB0045120DB";
$PAYMENT_URL   = "https://mtf.onepay.vn/paygate/vpcpay.op";
$QUERY_URL     = "https://mtf.onepay.vn/msp/api/v1/vpc/invoices/queries";

// Hàm tạo mã Hash theo chuẩn OnePAY (Mục II.8)
function generateHash($params, $secure_secret) {
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

$action = isset($_GET['action']) ? $_GET['action'] : '';

// -----------------------------------------------------------------------------
// XỬ LÝ CÁC ACTION (AJAX & RETURN)
// -----------------------------------------------------------------------------

// Xử lý AJAX: Trả về URL thanh toán
if ($action == 'get_payment_url') {
    $reqData = $_POST;
    $reqData['vpc_SecureHash'] = generateHash($reqData, $SECURE_SECRET);
    $redirectUrl = $PAYMENT_URL . "?" . http_build_query($reqData);
    
    header('Content-Type: application/json');
    echo json_encode(['url' => $redirectUrl]);
    exit();
}

// Xử lý AJAX: QueryDR API
if ($action == 'query_dr') {
    $queryData = $_POST;
    $queryData['vpc_SecureHash'] = generateHash($queryData, $SECURE_SECRET);
    
    // Gửi POST request dùng cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $QUERY_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($queryData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $queryResponseRaw = curl_exec($ch);
    curl_close($ch);
    
    parse_str($queryResponseRaw, $queryResponseArr);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $queryResponseArr]);
    exit();
}

// Xử lý Return URL (Hiển thị bên trong Popup Window sau khi thanh toán xong)
if ($action == 'return') {
    $responseData = $_GET;
    $receivedHash = isset($responseData['vpc_SecureHash']) ? $responseData['vpc_SecureHash'] : '';
    $calculatedHash = generateHash($responseData, $SECURE_SECRET);
    $isHashValid = ($receivedHash === $calculatedHash);
    $txnResponseCode = isset($responseData['vpc_TxnResponseCode']) ? $responseData['vpc_TxnResponseCode'] : 'Unknown';
    $isSuccess = ($txnResponseCode == "0");
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Kết quả thanh toán OnePAY</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full text-center border-t-8 <?php echo $isSuccess ? 'border-green-500' : 'border-red-500'; ?>">
            <?php if ($isSuccess): ?>
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <svg class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Giao Dịch Thành Công</h2>
            <?php else: ?>
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                    <svg class="h-10 w-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Giao Dịch Thất Bại</h2>
            <?php endif; ?>
            
            <div class="text-left bg-gray-50 p-4 rounded text-sm text-gray-600 mt-4 space-y-2 border">
                <p><span class="font-semibold">Mã phản hồi:</span> <?php echo $txnResponseCode; ?></p>
                <p><span class="font-semibold">Mã GD (MerchTxnRef):</span> <?php echo htmlspecialchars($responseData['vpc_MerchTxnRef'] ?? ''); ?></p>
                <p><span class="font-semibold">Check Hash:</span> 
                    <?php if($isHashValid): ?>
                        <span class="text-green-600 font-bold">Hợp lệ</span>
                    <?php else: ?>
                        <span class="text-red-600 font-bold">Sai Hash</span>
                    <?php endif; ?>
                </p>
            </div>

            <button onclick="window.close();" class="mt-6 w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                Đóng cửa sổ này
            </button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$defaultTxnRef = "TEST_" . time();
$currentUrlBase = "http://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER["REQUEST_URI"], '?');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnePAY Test Tool - Tailwind CSS</title>
    <!-- Thêm Tailwind CSS qua CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Tùy chỉnh thanh cuộn cho Modal hiển thị JSON */
        pre::-webkit-scrollbar { width: 8px; height: 8px; }
        pre::-webkit-scrollbar-track { background: #2d3748; }
        pre::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
        pre::-webkit-scrollbar-thumb:hover { background: #718096; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans text-gray-800 pb-12">

    <!-- Header -->
    <header class="bg-white shadow-sm mb-8">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <h1 class="text-3xl font-extrabold text-blue-900 tracking-tight">OnePAY Integration Test Tool</h1>
            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded border border-blue-400">Environment: TEST</span>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- CỘT 1: TẠO URL THANH TOÁN -->
            <div class="w-full lg:w-1/2 bg-white rounded-xl shadow-md overflow-hidden">
                <div class="bg-blue-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        1. Tạo yêu cầu thanh toán (Payment Request)
                    </h2>
                </div>
                <div class="p-6">
                    <form id="form-pay" class="space-y-4">
                        <?php 
                        // Mảng cấu hình các input field để code html ngắn gọn
                        $payFields = [
                            ['label' => 'vpc_Version', 'name' => 'vpc_Version', 'value' => '2'],
                            ['label' => 'vpc_Currency', 'name' => 'vpc_Currency', 'value' => 'VND'],
                            ['label' => 'vpc_Command', 'name' => 'vpc_Command', 'value' => 'pay'],
                            ['label' => 'vpc_AccessCode', 'name' => 'vpc_AccessCode', 'value' => '6BEB2546'],
                            ['label' => 'vpc_Merchant', 'name' => 'vpc_Merchant', 'value' => 'TESTONEPAY'],
                            ['label' => 'vpc_Locale', 'name' => 'vpc_Locale', 'value' => 'vn'],
                            ['label' => 'vpc_ReturnURL', 'name' => 'vpc_ReturnURL', 'value' => $currentUrlBase . "?action=return"],
                            ['label' => 'vpc_MerchTxnRef', 'name' => 'vpc_MerchTxnRef', 'value' => $defaultTxnRef, 'id' => 'pay_txnRef', 'highlight' => true],
                            ['label' => 'vpc_OrderInfo', 'name' => 'vpc_OrderInfo', 'value' => 'Don hang test'],
                            ['label' => 'vpc_Amount', 'name' => 'vpc_Amount', 'value' => '10000000', 'note' => '(Thêm 00 vào cuối - Vd: 100,000)'],
                            ['label' => 'vpc_TicketNo', 'name' => 'vpc_TicketNo', 'value' => '127.0.0.1'],
                            ['label' => 'Title', 'name' => 'Title', 'value' => 'Test Thanh Toan'],
                            ['label' => 'AgainLink', 'name' => 'AgainLink', 'value' => $currentUrlBase]
                        ];
                        foreach ($payFields as $field): 
                            $isHighlight = isset($field['highlight']) && $field['highlight'];
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center">
                            <label class="sm:w-1/3 text-sm font-medium text-gray-700 mb-1 sm:mb-0 <?php echo $isHighlight ? 'text-blue-600 font-bold' : ''; ?>">
                                <?php echo $field['label']; ?>:
                            </label>
                            <div class="sm:w-2/3">
                                <input type="text" 
                                    name="<?php echo $field['name']; ?>" 
                                    value="<?php echo $field['value']; ?>" 
                                    <?php echo isset($field['id']) ? 'id="'.$field['id'].'"' : ''; ?>
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm <?php echo $isHighlight ? 'bg-blue-50 border-blue-300' : 'bg-gray-50'; ?>">
                                <?php if(isset($field['note'])): ?>
                                    <p class="mt-1 text-xs text-gray-500 italic"><?php echo $field['note']; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="pt-4 border-t border-gray-200">
                            <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150">
                                Mở Popup Thanh Toán (OnePAY)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CỘT 2: QUERY DR -->
            <div class="w-full lg:w-1/2 bg-white rounded-xl shadow-md overflow-hidden self-start">
                <div class="bg-gray-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        2. Truy vấn QueryDR
                    </h2>
                </div>
                <div class="p-6">
                    <form id="form-query" class="space-y-4">
                        <?php 
                        $queryFields = [
                            ['label' => 'vpc_Command', 'name' => 'vpc_Command', 'value' => 'queryDR'],
                            ['label' => 'vpc_Version', 'name' => 'vpc_Version', 'value' => '2'],
                            ['label' => 'vpc_Merchant', 'name' => 'vpc_Merchant', 'value' => 'TESTONEPAY'],
                            ['label' => 'vpc_AccessCode', 'name' => 'vpc_AccessCode', 'value' => '6BEB2546'],
                            ['label' => 'vpc_User', 'name' => 'vpc_User', 'value' => 'op01'],
                            ['label' => 'vpc_Password', 'name' => 'vpc_Password', 'value' => 'op123456'],
                            ['label' => 'vpc_MerchTxnRef', 'name' => 'vpc_MerchTxnRef', 'value' => $defaultTxnRef, 'id' => 'query_txnRef', 'highlight' => true]
                        ];
                        foreach ($queryFields as $field): 
                            $isHighlight = isset($field['highlight']) && $field['highlight'];
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center">
                            <label class="sm:w-1/3 text-sm font-medium text-gray-700 mb-1 sm:mb-0 <?php echo $isHighlight ? 'text-blue-600 font-bold' : ''; ?>">
                                <?php echo $field['label']; ?>:
                            </label>
                            <div class="sm:w-2/3">
                                <input type="text" 
                                    name="<?php echo $field['name']; ?>" 
                                    value="<?php echo $field['value']; ?>" 
                                    <?php echo isset($field['id']) ? 'id="'.$field['id'].'"' : ''; ?>
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500 text-sm <?php echo $isHighlight ? 'bg-blue-50 border-blue-300' : 'bg-gray-50'; ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="pt-4 border-t border-gray-200 mt-6">
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">Mã <b>vpc_MerchTxnRef</b> được đồng bộ tự động với cột 1.</p>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition duration-150">
                                Gửi truy vấn QueryDR (AJAX)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal hiển thị kết quả Query DR (Tailwind CSS Modal) -->
    <div id="queryModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-gray-200">
                    <div class="sm:flex sm:items-start justify-between">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center" id="modal-title">
                                <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Chi tiết phản hồi API (JSON Data)
                            </h3>
                        </div>
                        <button onclick="closeModal()" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
                <div class="bg-gray-800 px-4 py-3 sm:px-6">
                    <!-- Khu vực hiển thị JSON -->
                    <pre id="query-result-json" class="text-green-400 font-mono text-sm whitespace-pre-wrap break-all max-h-96 overflow-y-auto">Đang truy vấn...</pre>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeModal()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 1. Đồng bộ giá trị vpc_MerchTxnRef giữa 2 cột
        const payTxnRef = document.getElementById('pay_txnRef');
        const queryTxnRef = document.getElementById('query_txnRef');

        payTxnRef.addEventListener('input', function() {
            queryTxnRef.value = this.value;
        });
        queryTxnRef.addEventListener('input', function() {
            payTxnRef.value = this.value;
        });

        // 2. Xử lý Form 1: Gọi AJAX lấy URL và mở Popup Window
        document.getElementById('form-pay').addEventListener('submit', function(e) {
            e.preventDefault(); 
            const formData = new FormData(this);
            
            // Thay đổi text button loading
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Đang xử lý...';
            btn.disabled = true;

            fetch('?action=get_payment_url', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.url) {
                    const w = 900;
                    const h = 700;
                    const left = (screen.width/2)-(w/2);
                    const top = (screen.height/2)-(h/2);
                    window.open(data.url, 'OnePayPaymentWindow', 'width='+w+',height='+h+',top='+top+',left='+left+',scrollbars=yes');
                }
            })
            .catch(error => console.error('Error:', error))
            .finally(() => {
                // Khôi phục button
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });

        // 3. Xử lý Form 2: Gọi AJAX Query DR và hiển thị Modal
        document.getElementById('form-query').addEventListener('submit', function(e) {
            e.preventDefault(); 
            const formData = new FormData(this);
            
            // Hiện Modal và set loading text
            document.getElementById('query-result-json').innerHTML = '<span class="text-gray-400 animate-pulse">⏳ Đang kết nối tới OnePAY server...</span>';
            document.getElementById('queryModal').classList.remove('hidden');

            fetch('?action=query_dr', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Tô màu JSON (Key màu hồng nhạt, String màu xanh lá) để dễ đọc hơn trong terminal mode
                    let formattedJson = JSON.stringify(data.data, null, 4);
                    formattedJson = formattedJson.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                        let cls = 'text-green-400'; // string value
                        if (/^"/.test(match)) {
                            if (/:$/.test(match)) {
                                cls = 'text-pink-400 font-semibold'; // key
                            }
                        } else if (/true|false/.test(match)) {
                            cls = 'text-blue-400'; // boolean
                        } else if (/null/.test(match)) {
                            cls = 'text-gray-500'; // null
                        } else {
                            cls = 'text-yellow-400'; // number
                        }
                        return '<span class="' + cls + '">' + match + '</span>';
                    });
                    document.getElementById('query-result-json').innerHTML = formattedJson;
                } else {
                    document.getElementById('query-result-json').innerHTML = '<span class="text-red-500">❌ Có lỗi xảy ra khi truy vấn dữ liệu.</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('query-result-json').innerHTML = '<span class="text-red-500">❌ Lỗi kết nối mạng hoặc server không phản hồi.</span>';
            });
        });

        // Đóng modal
        function closeModal() {
            document.getElementById('queryModal').classList.add('hidden');
        }
    </script>
</body>
</html>
