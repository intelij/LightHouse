<?php

use App\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ConfigTest extends TestCase
{
    private $configs = [];
    private $config_files = [
        "maillist"
    ];
    private $faker;

    public function setup()
    {
        parent::setup();
        foreach ($this->config_files as $config_file) {
            $this->configs[$config_file] = config($config_file);
        }
        $this->faker = Faker\Factory::create();
    }

    public function teardown()
    {
        foreach ($this->config_files as $config_file) {
            $config_data = var_export($this->configs[$config_file], 1);
            File::put(env("ROOT_PATH") . "/config/$config_file.php", "<?php\n return $config_data ;");//恢复现场
        }
        parent::teardown();

    }


    /**
     *Test whether maillist config can be edit
     */
    public function testVisit()
    {
        Session::start();
        $this->actingAs(User::find(15));

        $this->post(url("config/mail/seat-exchanged"), [
            "_token" => csrf_token(),
            "sender" => config("maillist.notify.seat_exchanged.sender"),
            "ccs" => implode(",", config("maillist.notify.seat_exchanged.ccs")),
            "initiator_subject" => "测试主题",
            "target_subject" => config("maillist.notify.seat_exchanged.target_subject"),
            "emergence_contact" => config("maillist.notify.seat_exchanged.emergence_contact")
        ])->assertResponseStatus(302)->assertEquals("测试主题", config("maillist.notify.seat_exchanged.initiator_subject"));


        $this->visit(url("config/mail/seat-exchanged"))
            ->type("randomStr", "emergence_contact")//should be false
            ->press("现在提交")
            ->assertNotEquals("randomStr",config("maillist.notify.seat_exchanged.emergence_contact"));

        $this->visit(url("config/mail/seat-exchanged"))
            ->type("test@test.com,randomStr", "ccs")//every address has to be email address.should be false
            ->press("现在提交")
            ->assertNotEquals(["test@test.com", "randomStr"], config("maillist.notify.seat_exchanged.ccs"));

        $this->visit(url("config/mail/seat-exchanged"))
            ->type("test@test.com,test2@test.com", "ccs")//
            ->press("现在提交")
            ->assertEquals(["test@test.com", "test2@test.com"], config("maillist.notify.seat_exchanged.ccs"));

    }
}
