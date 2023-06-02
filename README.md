# elasticsearch-reindexer
ElasticSearch fields type change and reindex data

tested with es 6

## Installation

The preferred way to install this extension is through composer:
```
composer require kr0lik/elasticsearch-reindexer
```

## Usage
Add into config/bundles.php:
```
kr0lik\ElasticSearchReindex\EsReindexBundle::class => ['all' => true],
```

Add `config/packages/es_reindex.yaml`

es_reindex.yaml config example:
```
  es_reindex:
    connection:
      host: '%env(resolve:key:host:url:ELASTICSEARCH_URL)%'
      port: '%env(resolve:key:port:url:ELASTICSEARCH_URL)%' 
      user: '%env(resolve:key:user:url:ELASTICSEARCH_URL)%'
      pass: '%env(resolve:key:pass:url:ELASTICSEARCH_URL)%'
    indices:
      -
        name: '<first-index>'
        script:
            source: 'ctx._source.doc.remove("timestamps")'
            lang: painless
        body:
          settings:
            index:
              max_result_window: 1000000
              analysis:
                normalizer:
                  standard:
                    type: custom
                    filter:
                      - lowercase
          mappings:
            _doc:
              properties:
                doc:
                  properties:
                    username:
                      type: keyword
                      normalizer: standard

      -
        name: '<second-index>'
        body:
          settings:
            index:
              max_result_window: 1000000
              analysis:
                normalizer:
                  standard:
                    type: custom
                    filter:
                      - lowercase
          mappings:
            _doc:
              properties:
                doc:
                  properties:
                    accountId:
                      type: long
```

Run command:
```
bin/console elastic-search:create-index <index-name-from-config>
```
