<?php

namespace leantime\domain\controllers {

    use leantime\core;
    use leantime\core\controller;
    use leantime\domain\repositories;
    use leantime\domain\services;
    use leantime\domain\models;

    class update extends controller
    {
        private repositories\install $installRepo;
        private repositories\setting $settingsRepo;
        private core\appSettings $appSettings;

        /**
         * init - initialize private variables
         *
         * @access public
         */
        public function init(
            repositories\install $installRepo,
            repositories\setting $settingsRepo,
            core\appSettings $appSettings
        ) {
            $this->installRepo = $installRepo;
            $this->settingsRepo = $settingsRepo;
            $this->appSettings = $appSettings;
        }

        /**
         * get - handle get requests
         *
         * @access public
         * @params parameters or body of the request
         */
        public function get($params)
        {
            $dbVersion = $this->settingsRepo->getSetting("db-version");
            if ($this->appSettings->dbVersion == $dbVersion) {
                core\frontcontroller::redirect(BASE_URL . "/auth/login");
            }

            $this->tpl->display("install.update", "entry");
        }

        public function post($params)
        {


            if (isset($_POST['updateDB'])) {
                $success = $this->installRepo->updateDB();

                if (is_array($success) === true) {
                    foreach ($success as $errorMessage) {

                        error_log($errorMessage);

                    }
                    $this->tpl->setNotification("There was a problem updating your database. Please check your error logs to verify your database is up to date.", "error");
                    core\frontcontroller::redirect(BASE_URL . "/install/update");
                }

                if ($success === true) {
                    $this->tpl->setNotification(sprintf($this->language->__("text.update_was_successful"), BASE_URL), "success");
                    core\frontcontroller::redirect(BASE_URL);
                }
            }
        }
    }

}
