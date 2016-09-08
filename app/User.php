<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;

class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Where User has corresponding role
     * @param $role
     * @return bool
     */
    public function hasRole($role)
    {
        return !(strpos($this->archive->Identity, $role) === false);
    }

    public function archive()
    {
        return $this->hasOne('App\UserArchive', 'id');
    }

    /**获取身份数组
     * @return bool|array
     */
    public function identities()
    {
        $identity_string = $this->archive->Identity;
        if ($identity_string != null && $identity_string != "") {
            $identities = explode(",",$identity_string);
            foreach ($identities as &$identity) {
                switch ($identity) {
                    case "ADMIN":
                        $identity = "管理员";
                        break;
                    case "DAIS":
                        $identity = "主席";
                        break;
                    case "OT":
                        $identity = "会务运营团队";
                        break;
                    case "AT":
                        $identity = "学术管理团队";
                        break;
                    case "DIR":
                        $identity = "理事";
                        break;
                    case "COREDIR":
                        $identity = "核心理事"
                        ;break;
                    case "VOL":
                        $identity = "志愿者"
                        ;break;
                    case "DEL":
                        $identity = "代表"
                        ;break;
                    case "HEADDEL":
                        $identity = "代表团领队"
                        ;break;
                    case "OTHER":
                        $identity = "其他"
                        ;break;
                }
            }
            return $identities;
        }
        return false;
    }
    
    public function getIdentitiesAttribute(){
        return $this->identities();
    }

}
