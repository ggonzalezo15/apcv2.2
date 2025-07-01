-- ============================================================================
-- MIGRACIÓN: Agregar columna status a tabla incomes
-- Fecha: 2025-01-15
-- Descripción: Agrega columna status para manejar estados: pending, paid, overpaid
-- ============================================================================

-- Paso 1: Agregar la columna status a la tabla incomes
ALTER TABLE `incomes` 
ADD COLUMN `status` ENUM('pending', 'paid', 'overpaid') NOT NULL DEFAULT 'pending' 
AFTER `total_amount`;

-- Paso 2: Agregar índice para la columna status (para optimizar consultas con filtros)
ALTER TABLE `incomes` 
ADD INDEX `idx_status` (`status`);

-- Paso 3: Crear función para calcular el status de un ingreso específico
DELIMITER $$

DROP FUNCTION IF EXISTS CalculateIncomeStatus$$

CREATE FUNCTION CalculateIncomeStatus(income_id_param CHAR(36))
RETURNS ENUM('pending', 'paid', 'overpaid')
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_income DECIMAL(15,2) DEFAULT 0.00;
    DECLARE total_payments DECIMAL(15,2) DEFAULT 0.00;
    DECLARE balance_amount DECIMAL(15,2) DEFAULT 0.00;
    DECLARE calculated_status ENUM('pending', 'paid', 'overpaid') DEFAULT 'pending';
    
    -- Calcular total de líneas de ingreso
    SELECT COALESCE(SUM(total_amount), 0) INTO total_income
    FROM income_lines 
    WHERE income_id = income_id_param;
    
    -- Calcular total de pagos recibidos
    SELECT COALESCE(SUM(amount), 0) INTO total_payments
    FROM income_payments 
    WHERE income_id = income_id_param;
    
    -- Calcular balance
    SET balance_amount = total_income - total_payments;
    
    -- Determinar status basado en el balance
    IF balance_amount > 0 THEN
        SET calculated_status = 'pending';
    ELSEIF balance_amount = 0 THEN
        SET calculated_status = 'paid';
    ELSE
        SET calculated_status = 'overpaid';
    END IF;
    
    RETURN calculated_status;
END$$

DELIMITER ;

-- Paso 4: Crear procedimiento para actualizar el status de un ingreso específico
DELIMITER $$

DROP PROCEDURE IF EXISTS UpdateIncomeStatus$$

CREATE PROCEDURE UpdateIncomeStatus(IN income_id_param CHAR(36))
BEGIN
    DECLARE new_status ENUM('pending', 'paid', 'overpaid');
    
    -- Calcular el nuevo status
    SET new_status = CalculateIncomeStatus(income_id_param);
    
    -- Actualizar el status en la tabla
    UPDATE incomes 
    SET status = new_status, updated_at = CURRENT_TIMESTAMP
    WHERE id = income_id_param;
END$$

DELIMITER ;

-- Paso 5: Crear procedimiento para recalcular todos los status existentes
DELIMITER $$

DROP PROCEDURE IF EXISTS RecalculateAllIncomeStatus$$

CREATE PROCEDURE RecalculateAllIncomeStatus()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE current_income_id CHAR(36);
    DECLARE income_cursor CURSOR FOR 
        SELECT id FROM incomes;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN income_cursor;
    
    read_loop: LOOP
        FETCH income_cursor INTO current_income_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        CALL UpdateIncomeStatus(current_income_id);
    END LOOP;
    
    CLOSE income_cursor;
END$$

DELIMITER ;

-- Paso 6: Crear triggers para actualizar automáticamente el status
-- Trigger para cuando se insertan/actualizan/eliminan líneas de ingreso
DELIMITER $$

DROP TRIGGER IF EXISTS income_lines_after_insert$$
CREATE TRIGGER income_lines_after_insert
    AFTER INSERT ON income_lines
    FOR EACH ROW
BEGIN
    CALL UpdateIncomeStatus(NEW.income_id);
END$$

DROP TRIGGER IF EXISTS income_lines_after_update$$
CREATE TRIGGER income_lines_after_update
    AFTER UPDATE ON income_lines
    FOR EACH ROW
BEGIN
    CALL UpdateIncomeStatus(NEW.income_id);
END$$

DROP TRIGGER IF EXISTS income_lines_after_delete$$
CREATE TRIGGER income_lines_after_delete
    AFTER DELETE ON income_lines
    FOR EACH ROW
BEGIN
    CALL UpdateIncomeStatus(OLD.income_id);
END$$

-- Trigger para cuando se insertan/actualizan/eliminan pagos
DROP TRIGGER IF EXISTS income_payments_after_insert$$
CREATE TRIGGER income_payments_after_insert
    AFTER INSERT ON income_payments
    FOR EACH ROW
BEGIN
    CALL UpdateIncomeStatus(NEW.income_id);
END$$

DROP TRIGGER IF EXISTS income_payments_after_update$$
CREATE TRIGGER income_payments_after_update
    AFTER UPDATE ON income_payments
    FOR EACH ROW
BEGIN
    CALL UpdateIncomeStatus(NEW.income_id);
END$$

DROP TRIGGER IF EXISTS income_payments_after_delete$$
CREATE TRIGGER income_payments_after_delete
    AFTER DELETE ON income_payments
    FOR EACH ROW
BEGIN
    CALL UpdateIncomeStatus(OLD.income_id);
END$$

DELIMITER ;

-- Paso 7: Ejecutar el recálculo de todos los status existentes
CALL RecalculateAllIncomeStatus();

-- Paso 8: Verificar los resultados
SELECT 
    'Total de registros' as descripcion,
    COUNT(*) as cantidad
FROM incomes
UNION ALL
SELECT 
    CONCAT('Status: ', status) as descripcion,
    COUNT(*) as cantidad
FROM incomes 
GROUP BY status
ORDER BY descripcion;

-- ============================================================================
-- INSTRUCCIONES DE USO:
-- ============================================================================
-- 1. Ejecutar este script en tu base de datos
-- 2. El status se actualizará automáticamente cuando se modifiquen líneas o pagos
-- 3. Para recalcular manualmente todos los status: CALL RecalculateAllIncomeStatus();
-- 4. Para actualizar un status específico: CALL UpdateIncomeStatus('id_del_ingreso');
-- ============================================================================ 