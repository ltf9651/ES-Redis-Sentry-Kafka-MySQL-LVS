<?php

namespace app\controllers;

use yii\web\Controller;
use app\models\Category;
use app\models\Cart;
use app\models\User;
use app\models\Product;
use Yii;

class CommonController extends Controller
{
    public function init()
    {
        // 菜单缓存
        $cache = Yii::$app->cache;
        $key = 'menu';
        if (!$menu = $cache->get($key)) {
            //没有获取到就进行查询
            $menu = Category::getMenu();
            //查询到的数据录入redis, 降低DB压力
            $cache->set($key, $menu, 3600 * 2);
        }

//        $menu = Category::getMenu();
        $menu = $cache->get($key);
        $this->view->params['menu'] = $menu;

        //购物车缓存
        $key = 'cart';
        if (!$data = $cache->get($key)) {
            $data = Category::getMenu();
            $cache->set($key, $data, 3600 * 2);


            $data = [];
            $data['products'] = [];
            $total = 0;
            if (Yii::$app->session['isLogin']) {
                $usermodel = User::find()->where('username = :name', [":name" => Yii::$app->session['loginname']])->one();
                if (!empty($usermodel) && !empty($usermodel->userid)) {
                    $userid = $usermodel->userid;
                    $carts = Cart::find()->where('userid = :uid', [':uid' => $userid])->asArray()->all();
                    foreach ($carts as $k => $pro) {
                        $product = Product::find()->where('productid = :pid', [':pid' => $pro['productid']])->one();
                        $data['products'][$k]['cover'] = $product->cover;
                        $data['products'][$k]['title'] = $product->title;
                        $data['products'][$k]['productnum'] = $pro['productnum'];
                        $data['products'][$k]['price'] = $pro['price'];
                        $data['products'][$k]['productid'] = $pro['productid'];
                        $data['products'][$k]['cartid'] = $pro['cartid'];
                        $total += $data['products'][$k]['price'] * $data['products'][$k]['productnum'];
                    }
                }
            }
            $data['total'] = $total;
            //缓存依赖（实现实时更新
            $dep = new \yii\caching\DbDependency([
                'sql' => 'select max(update_time) from {{%cart%}} where userid = :uid',
                'param' => [':uid' => Yii::$app->user->id],
            ]);
            //$dep 缓存查询时先查看$dep查看购物车状态是否为最新，将缓存也更新至最新状态
            $cache->set($key, $data, 60, $dep);
        }
        $this->view->params['cart'] = $data;

        //对商品查询缓存
        $dep_ = new \yii\caching\DbDependency([
            'sql' => 'select max(update_time) from {{%product%}} where istui = 1',
        ]);//缓存依赖
        $tui = Product::getDb()->cache(function () {
            //将此查询语句加入缓存
            return Product::find()->where('istui = "1" and ison = "1"')->orderby('createtime desc')->limit(3)->all();
        }, 60, $dep_);
        //$tui = Product::find()->where('istui = "1" and ison = "1"')->orderby('createtime desc')->limit(3)->all();
        $new = Product::find()->where('ison = "1"')->orderby('createtime desc')->limit(3)->all();
        $hot = Product::find()->where('ison = "1" and ishot = "1"')->orderby('createtime desc')->limit(3)->all();
        $sale = Product::find()->where('ison = "1" and issale = "1"')->orderby('createtime desc')->limit(3)->all();
        $this->view->params['tui'] = (array)$tui;
        $this->view->params['new'] = (array)$new;
        $this->view->params['hot'] = (array)$hot;
        $this->view->params['sale'] = (array)$sale;
    }
}
