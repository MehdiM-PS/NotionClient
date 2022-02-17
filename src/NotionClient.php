<?php


namespace Psmidmarket;

require_once('vendor/autoload.php');

use GuzzleHttp\Client;

class NotionClient
{
    private $token;
    private $version;
    private $headers;
    private $client;
    private $basePath = 'https://api.notion.com/v1/';
    private $debug;

    public function __construct(string $token, string $version, bool $debug = false)
    {
        $this->token = $token;
        $this->version = $version;
        $this->debug = $debug;

        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Notion-Version' => $this->version,
            'Content-Type' => 'application/json',
        ];

        $this->client = new \GuzzleHttp\Client();
    }

    public function getBlocks($block_id): ?object
    {
        try {
            $response = $this->client->request('GET', $this->basePath . 'blocks/' . $block_id . '/children', [
                'headers' => $this->headers,
                'body' => '{}',
            ]);
            return json_decode($response->getBody());
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            if ($this->debug) {
                die($e);
            } else {
                return null;
            }
        }
    }

    public function getPageTitle($page_id): string
    {
        try {
            $response = $this->client->request('GET', $this->basePath . 'pages/' . $page_id, [
                'headers' => $this->headers,
                'body' => '{}',
            ]);
            $title = json_decode($response->getBody());
            $html = '<h1>';
            foreach ($title->properties->title->title as $text) {
                $html .= $this->displayAnnotedText($text);
            }
            $html .= '</h1>';
            return $html;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            if ($this->debug) {
                die($e);
            } else {
                return '';
            }
        }
    }

    public function isParentBlock($block): bool
    {
        return (isset($block->has_children) && $block->has_children);
    }

    public function displayBlock($block_id): string
    {
        $html = '';
        $blocks = $this->getBlocks($block_id);
        if (!isset($blocks->results) || !count($blocks->results)) {
            return '';
        }
        foreach ($blocks->results as $block) {
            if ($this->isParentBlock($block)) {
                $html .= $this->getHtml($block);
                $html .= $this->displayBlock($block->id);
                $html .= $this->getHtml($block, true);
            } else {
                $html .= $this->getHtml($block);
            }
        }
        return $html;
    }

    public function displayAnnotedText($text)
    {
        $html = '';
        if (isset($text->annotations)) {
            $html .= $this->getHtmlProperties($text->annotations);
        }
        if ($text->href) {
            $html .= '<a href="' . $text->href . '">';
        }
        $html .= $text->plain_text;
        if ($text->href) {
            $html .= '</a>';
        }
        if (isset($text->annotations)) {
            $html .= $this->getHtmlProperties($text->annotations, true);
        }
        return $html;
    }

    public function getHtml($block, $closing_tag = false)
    {
        switch ($block->type) {
            case 'heading_1':
                $html = '<h1 class="notion_h1">';
                foreach ($block->heading_1->text as $text) {
                    $html .= $this->displayAnnotedText($text);
                }
                $html .= '</h1>';
                return $html;
                break;
            case 'heading_2':
                $html = '<h2 class="notion_h2">';
                foreach ($block->heading_2->text as $text) {
                    $html .= $this->displayAnnotedText($text);
                }
                $html .= '</h2>';
                return $html;
                break;
            case 'heading_3':
                $html = '<h3 class="notion_h3">';
                foreach ($block->heading_3->text as $text) {
                    $html .= $this->displayAnnotedText($text);
                }
                $html .= '</h3>';
                return $html;
                break;
            case 'paragraph':
                $html = '';
                if (count($block->paragraph->text)) {
                    $html .= '<p class="notion_paragraph">';
                    foreach ($block->paragraph->text as $text) {
                        $html .= $this->displayAnnotedText($text);
                    }
                    $html .= '</p>';
                    return $html;
                } else {
                    return '<br/>';
                }
                break;
            case 'bulleted_list_item':
                $html = '<ul class="notion_list"><li>';
                foreach ($block->bulleted_list_item->text as $text) {
                    $html .= $this->displayAnnotedText($text);
                }
                $html .= '</li></ul>';
                return $html;
                break;
            case 'numbered_list_item':
                $html = '<ol class="notion_list"><li>';
                foreach ($block->numbered_list_item->text as $text) {
                    $html .= $this->displayAnnotedText($text);
                }
                $html .= '</li></ol>';
                return $html;
                break;
            case 'table':
                return ((!$closing_tag) ? '<table class="table notion_table">' : '</table>');
                break;
            case 'table_row':
                $html = '<tr>';
                if (isset($block->table_row->cells) && count($block->table_row->cells)) {
                    foreach ($block->table_row->cells as $cell_array) {
                        $html .= '<td>';
                        foreach ($cell_array as $cell) {
                            if ($cell->type === 'text') {
                                $html .= $cell->plain_text;
                            }
                        }
                        $html .= '</td>';
                    }
                }
                $html .= '</tr>';
                return $html;
                break;
            case 'image':
                $html = '<div class="notion_img">';
                if ($block->image->type === 'external') {
                    $html .= '<img class="img-fluid" src="' . $block->image->external->url . '">';
                } else {
                    $html .= '<img class="img-fluid" src="' . $block->image->file->url . '">';
                }
                $html .= '</div>';
                return $html;
                break;
            default:
                if ($this->debug) {
                    echo '<pre>';
                    var_dump($block);
                    echo '</pre>';
                }
                break;
        }
    }

    public function getHtmlProperties($annotations, $closing_tag = false): string
    {
        $html = ($annotations->color !== 'default' ? (!$closing_tag ? '<span class="notion_color ' . $annotations->color . '" style="color:' . $annotations->color . ';">' : '</span>') : '');
        $html .= ($annotations->bold ? (!$closing_tag ? '<strong>' : '</strong>') : '');
        $html .= ($annotations->italic ? (!$closing_tag ? '<span style="font-style:italic;">' : '</span>') : '');
        $html .= ($annotations->strikethrough ? (!$closing_tag ? '<span style="text-decoration:line-through;">' : '</span>') : '');
        $html .= ($annotations->underline ? (!$closing_tag ? '<span style="border-bottom: 0.05em solid">' : '</span>') : '');
        return $html;
    }
}
