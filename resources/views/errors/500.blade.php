@include('errors.layout', [
    'code' => 500,
    'heading' => __('حدث خطأ من جانبنا'),
    'message' => __('نعتذر عن ذلك. فريقنا يتابع المشكلة. حاول مرة أخرى بعد قليل.'),
    'requestId' => request()->attributes->get('request_id'),
])
