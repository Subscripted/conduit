CREATE TABLE ki_models(
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    model_id VARCHAR(100) NOT NULL,
    price_request_mio_dollars DECIMAL(8,2) NOT NULL DEFAULT '0.00',
    price_response_mio_dollars DECIMAL(8,2) NOT NULL DEFAULT '0.00',
    information TEXT,
    provider VARCHAR(100) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY model_id (model_id)
)

