<?php
add_action('rest_api_init', function () {
  register_rest_route('twispeer/v1', '/feed', array(
    'methods' => 'GET',
    'callback' => 'twispeer_get_feed',
  ));
  register_rest_route('twispeer/v1', '/post', array(
    'methods' => 'POST',
    'callback' => 'twispeer_create_post',
    'permission_callback' => function () { return is_user_logged_in(); }
  ));
});

function twispeer_get_feed($request) {
  $items = array(
    array('id'=>1,'text'=>'I love rainy mornings.','time'=>'2h','reactions'=>array('❤️'=>4,'😂'=>1)),
    array('id'=>2,'text'=>'Coffee first, questions later.','time'=>'3h','reactions'=>array('☕'=>6)),
    array('id'=>3,'text'=>'Sometimes silence is an answer.','time'=>'1d','reactions'=>array('🤫'=>3)),
  );
  return rest_ensure_response($items);
}

function twispeer_create_post($request) {
  $params = $request->get_json_params();
  $text = sanitize_text_field($params['text'] ?? '');
  if (empty($text)) {
    return new WP_Error('empty_text', 'Text is required', array('status'=>400));
  }
  $new = array('id'=>rand(100,999),'text'=>$text,'time'=>'just now','reactions'=>array());
  return rest_ensure_response($new);
}
