ALTER TABLE `usuarios`
  ADD UNIQUE KEY IF NOT EXISTS `uq_usuarios_nombre_usuario` (`nombre_usuario`),
  ADD UNIQUE KEY IF NOT EXISTS `uq_usuarios_correo` (`correo`);