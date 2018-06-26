<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use App\Http\Requests\Api\UserRequest;
use App\Models\Image;
use App\Transformers\UserTransformer;
use App\Http\Requests\Api\AuthorizationRequest;
use App\Http\Requests\Api\SocialAuthorizationRequest;

class WeChatSigninController extends Controller
{
  
  // public function store(AuthorizationRequest $request)
  //   {
  //       $username = $request->username;

  //       filter_var($username, FILTER_VALIDATE_EMAIL) ?
  //           $credentials['email'] = $username :
  //           $credentials['phone'] = $username;

  //       $credentials['password'] = $request->password;

  //       if (!$token = Auth::guard('api')->attempt($credentials)) {
  //           return $this->response->errorUnauthorized('用户名或密码错误');
  //       }

  //       return $this->respondWithToken($token)->setStatusCode(201);
  //   }

    // public function store(UserRequest $request)
    // {
    // 		$phone = $request->phone;
    //         $credentials['phone'] = $phone;
    //           $credentials['password'] = "1234";
    //         //$credentials['phone'] = $username;
    //     $verifyData = \Cache::get($request->verification_key);

    //     if (!$verifyData) {
    //         return $this->response->error('验证码已失效', 422);
    //     }

    //     if (!hash_equals($verifyData['code'], $request->verification_code)) {
    //         // 返回401
    //         return $this->response->errorUnauthorized('验证码错误');
    //     }

    //     if(User::where(['phone'=>$phone])->first())
    //     {


        

        
    //     		return $this->response->array([
    //         'login' => 'true',
    //        'token'=> respondWithToken($token)
    //       	])->setStatusCode(201);
    //     }
    //     else
    //     {
    //     	 $user = User::create([
           
    //         'phone' => $verifyData['phone'],
         
    //     ]);

    //     // 清除验证码缓存
    //     \Cache::forget($request->verification_key);

    //     return $this->response->created();
    //     }

       
    // }
   public function store(UserRequest $request)
   {

     $verifyData = \Cache::get($request->verification_key);

        if (!$verifyData) {
            return $this->response->error('验证码已失效', 422);
        }

        // 判断验证码是否相等，不相等反回 401 错误
        if (!hash_equals((string)$verifyData['code'], $request->verification_code)) {
            return $this->response->errorUnauthorized('验证码错误');
        }

        // 获取微信的 openid 和 session_key
        $miniProgram = \EasyWeChat::miniProgram();
        $data = $miniProgram->auth->session($request->code);

        if (isset($data['errcode'])) {
            return $this->response->errorUnauthorized('code 不正确');
        }

        // 如果 openid 对应的用户已存在，报错403
        $user = User::where('weapp_openid', $data['openid'])->first();

        if ($user) {
            return $this->response->errorForbidden('微信已绑定其他用户，请直接登录');
        }

        // 创建用户
        $user = User::create([
            'name' => $request->name,
            'phone' => $verifyData['phone'],
            'password' => bcrypt($request->password),
            'weapp_openid' => $data['openid'],
            'weixin_session_key' => $data['session_key'],
        ]);

        // 清除验证码缓存
        \Cache::forget($request->verification_key);

        // meta 中返回 Token 信息
        return $this->response->item($user, new UserTransformer())
            ->setMeta([
                'access_token' => \Auth::guard('api')->fromUser($user),
                'token_type' => 'Bearer',
                'expires_in' => \Auth::guard('api')->factory()->getTTL() * 60
            ])
            ->setStatusCode(201);
   }
    
   
    public function respondWithToken($token)
      {
          return $this->response->array([
              'access_token' => $token,
              'token_type' => 'Bearer',
              'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
          ]);
      }
    }
