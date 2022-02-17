How to use : 

> $notion = new Psmidmarket\NotionClient('secret_token', 'api-version');

> echo $notion->getPageTitle('page_id');

> echo $notion->displayBlock('page_id'); // recursively render (almost) all your page's blocks in HTML
