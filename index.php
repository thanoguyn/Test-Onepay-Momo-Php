<?php
/**
 * TRANG CHỦ & HƯỚNG DẪN SỬ DỤNG
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Công cụ Tích Hợp Cổng Thanh Toán</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 py-12 font-sans">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-lg border border-gray-200">
        <h1 class="text-3xl font-bold text-center text-blue-800 mb-8">Khu Vực Kiểm Thử Cổng Thanh Toán</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <a href="momo.php" class="block p-6 bg-pink-50 border border-pink-200 rounded-xl hover:shadow-md hover:bg-pink-100 transition">
                <h2 class="text-xl font-bold text-pink-600 mb-2">Thanh Toán MoMo</h2>
                <p class="text-gray-600 text-sm">Công cụ tự động tạo URL thanh toán QRCode/App, giả lập IPN, Webhook và tra cứu đơn hàng MoMo.</p>
            </a>
            
            <a href="onepay.php" class="block p-6 bg-blue-50 border border-blue-200 rounded-xl hover:shadow-md hover:bg-blue-100 transition">
                <h2 class="text-xl font-bold text-blue-700 mb-2">Thanh Toán OnePAY</h2>
                <p class="text-gray-600 text-sm">Công cụ bóc tách chữ ký bảo mật, giả lập request và test OnePAY Sandbox qua thẻ nội địa/quốc tế.</p>
            </a>
        </div>

        <div class="prose max-w-none text-gray-700 border-t pt-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Hướng Dẫn Sử Dụng Nhanh</h2>
            <p>Dự án này là bộ công cụ All-in-One dùng cho kỹ thuật viên kiểm thử giao dịch thanh toán.</p>
            
            <h3 class="text-lg font-semibold mt-4 text-blue-700">1. Cách chạy ứng dụng trên máy lẻ</h3>
            <ul class="list-disc pl-5 mb-4 text-sm whitespace-normal space-y-1">
                <li>Sử dụng các môi trường PHP như XAMPP, MAMP, Laragon, WAMP.</li>
                <li>Hoặc có thể bật terminal ở thư mục này và gõ: <code class="bg-gray-200 text-red-500 px-1 py-0.5 rounded">php -S localhost:8000</code>, sau đó truy cập <a href="http://localhost:8000" class="text-blue-500 underline font-medium">http://localhost:8000</a>.</li>
            </ul>

            <h3 class="text-lg font-semibold mt-4 text-blue-700">2. Tính năng tích hợp của từng bài Test</h3>
            <ul class="list-disc pl-5 mb-4 text-sm space-y-2">
                <li><strong>Khởi tạo giao dịch:</strong> Click qua từng trang (MoMo/OnePAY) để chạy test tạo link thanh toán thực tế dựa trên Endpoint và Config Sandbox.</li>
                <li><strong>Giả lập IPN / Return (Mock):</strong> Sau khi tạo cấu hình, bạn có thể sinh ra các URL giả lập trả về giao dịch thành công / thất bại nhằm kiểm thử đoạn code Webhook của hệ thống nội bộ của bạn.</li>
                <li><strong>Gỡ rối chữ ký (Signature Debug):</strong> Cung cấp tính năng bóc tách tham số GET/POST để tự động sắp xếp và sinh lại mã Hash chuẩn, giúp bạn đối chiếu xem lỗi tạo chữ ký (Invalid Signature) phát sinh do khuyết dữ liệu hay sai Key.</li>
                <li><strong>Truy vấn (QueryDR/Check Order):</strong> Test API gọi từ máy chủ (Server-to-Server) lên hệ thống thanh toán để hỏi trạng thái chuẩn dựa trên OrderId / vpc_MerchTxnRef do bạn sinh ra.</li>
            </ul>

            <p class="mt-6 text-sm text-yellow-800 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                <strong>Lưu ý:</strong> API Key / Secret Key có sẵn trong source là bản thử nghiệm của đối tác cung cấp, vui lòng chỉ sử dụng cho mục đích lập trình & test Sandbox. <br/>Bạn không copy chúng lên dự án thật nhé. Đọc thêm tại <b>readme.md</b>.
            </p>
        </div>
    </div>
</body>
</html>
