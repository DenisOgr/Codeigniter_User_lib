/*
 * FUNCTION: Отправляет токен от соц. сети для регистрации/авторизации пользователя (для авторизации в режиме boolean)
 * IN PARAM: string token
 * OUT PARAM: view
 */

function handler_social(token) {
    token = token || false;
    if (token) {
        var url = SITE_URL + 'user/set_user';
        $.post(url, {token:token}, function (data) {
            if (data.status == 0) {
                alert('Error');
                return;
            }
           //мой обработчик
            $('#result').html(data.data);

        }, 'json');
    } else {
        alert('Error')
    }
    ;
}
