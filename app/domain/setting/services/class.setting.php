<?php

namespace leantime\domain\services {

    use leantime\core;
    use leantime\domain\repositories;

    class setting
    {
        private core\template $tpl;
        public repositories\setting $settingsRepo;

        public function __construct(
            core\template $tpl,
            repositories\setting $settingsRepo
        ) {
            $this->tpl = $tpl;
            $this->settingsRepo = $settingsRepo;
        }

        public function setLogo($file)
        {

            $upload = app()->make(core\fileupload::class);

            $upload->initFile($file['file']);

            $newname = md5($_SESSION['userdata']['id'] . time());
            $upload->renameFile($newname);

            if ($upload->error == '') {
                $url = $upload->uploadPublic();

                if ($url !== false) {
                    $this->settingsRepo->saveSetting("companysettings.logoPath", $url);

                    if (strpos($url, 'http') === 0) {
                        $_SESSION["companysettings.logoPath"] = $url;
                    } else {
                        $_SESSION["companysettings.logoPath"] = BASE_URL . $url;
                    }

                    return true;
                }
            }
        }

        public function resetLogo() {

            $url = '/dist/images/logo.svg';

            $this->settingsRepo->saveSetting("companysettings.logoPath", $url);

            $_SESSION["companysettings.logoPath"] = BASE_URL . $url;

        }

        public function saveSetting($key, $value){
            return $this->settingsRepo->saveSetting($key, $value);
        }

        public function getSetting($key){
            return $this->settingsRepo->getSetting($key);
        }

        /**
         * @return repositories\setting
         */
        public function getSettingsRepo(): repositories\setting
        {
            return $this->settingsRepo;
        }

        /**
         * @param repositories\setting $settingsRepo
         */
        public function setSettingsRepo(repositories\setting $settingsRepo): void
        {
            $this->settingsRepo = $settingsRepo;
        }


    }

}
